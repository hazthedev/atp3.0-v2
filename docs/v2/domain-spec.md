# ATP 3.0 v2 — Domain Specification

> **Purpose.** A precise, code-verified statement of the **business rules** ATP 3.0 v2
> must preserve. This is a SPEC, not a port — it describes WHAT the system does, in
> plain language plus exact logic, cited by symbol against the READ-ONLY atp3.0
> reference codebase. v2 may improve HOW (see `improvements.md`); it must not change
> WHAT without sign-off. Every **ASK** item in §6 is a decision for the user before
> Phase 5 porting.
>
> **Status.** Phase 2 draft for `[REVIEW]`. Extracted from atp3.0 by four parallel
> read-only analyses (counter/penalty, flight pipeline, readiness/airworthiness, MRO).
> Nothing here is implemented yet.
>
> **Legend.** Rules are stated as **Rule** (plain language) — *Logic:* (precise) —
> *Source:* `Class::method`. Status of compliance/readiness uses the originals'
> vocabulary.

---

## 0. Cross-cutting: concurrency & the "SQLite no-op" trap

This is the single most important inherited risk and the brief's explicit do-not-re-inherit item.

**What's actually true in the current code (corrected from the older brain doc):** real
locking *has* been added to the **counter** paths — `lockForUpdate()` inside
`DB::transaction`, create-on-first-use via `CounterWriteGuard::lockOrCreate`, batch writes
with `DB::transaction(fn, 3)` deadlock-retry, and `CounterUpdated implements
ShouldDispatchAfterCommit`. **But the protection is unproven**, and the **MRO paths have no
locking at all**:

- The default test driver is **in-memory SQLite** (`phpunit.xml`: `DB_CONNECTION=sqlite`,
  `:memory:`), where `lockForUpdate()` is a **silent no-op** — so the normal suite cannot
  prove serialization.
- The only real proof, `tests/Feature/Services/CounterLostUpdateMySqlConcurrencyTest`,
  **`markTestSkipped`s** unless `DB_TEST_HOST` + `DB_TEST_DATABASE` point at a migrated
  MySQL DB, and it is **excluded from both** `composer test:counters-reliability` **and**
  `test:counters-release-gate`. It effectively never runs.
- `lockForUpdate` appears in **7 sites, all in the counter engine; none on any MRO model.**
- There is **no concurrent/parallel test** anywhere (e.g. `NextCodeTest` is single-threaded).

**v2 mandate:** keep the counter locking, add the missing MRO protection, and **prove every
guard against MySQL in CI** (provision a MySQL service in the pipeline; include the
concurrency test in the release gate). SQLite passing is not evidence.

### Concurrency hotspot summary (detail in each domain section)

| # | Path | Race | Current state | v2 requirement |
|---|------|------|---------------|----------------|
| H1 | Counter read-modify-write (all 5 write paths) | Lost update — a flight's FH/FC silently dropped | `lockForUpdate` in txn (untested) | Keep FOR UPDATE; add optimistic `version` column as portable 2nd line; prove on MySQL |
| H2 | Batch `applyRows` | Deadlock (1213) from opposing lock order | one txn + 3 retries | Deterministic lock ordering (sort by id) + bounded retry |
| H3 | Penalty/propagation cascade | Lock set grows with installed-base depth | inside caller txn under FOR UPDATE | Keep; deterministic ordering matters more here |
| H4 | API idempotency (`counter_event_ingestions`) | Two same-key requests both pass the read check | `idempotency_key` UNIQUE exists; check is a non-locked read | Rely on the unique constraint (insert-or-catch), not the read |
| H5 | Penalty static-term threshold edge | Two concurrent events both cross the edge → static applied twice | in-memory `$seen`, relies entirely on row lock | Decide once-ever vs once-per-event (ASK C2); persist a fire-record if once-ever |
| H6 | Late-event recalc | Recalc clobbers a live write | txn + `lockForUpdate` + scheduler `withoutOverlapping` | Keep; needs a real cache-lock store in prod |
| H7 | MRO code generation (`NextCode`) | Two creates compute same code → unique-violation 500 | read-then-write, **no lock**; `unique('code')` backstop turns race into a 500 | Generate inside insert txn w/ locked sequence + catch-unique-retry |
| H8 | WP build from session selection | Double-submit → two WPs + duplicate tasks | session pick, **no idempotency** | One-shot token / consumed server-side draft |
| H9 | Record Completion | Double-record → duplicate compliance rows | `updateOrCreate` on a **non-unique** index | **UNIQUE** index on `(fl_id,item_id,counter_ref_id)` + UI debounce |
| H10 | All MRO saves | Last-write-wins, lost edits; diff-and-prune deletes the other editor's new rows | `findOrFail→fill→save`, **no version check** | Optimistic concurrency (version / `updated_at` guard) |
| H11 | Two compliance writers (`MaintenanceCompletionRecorder`, Tech-Pubs `recordAction`) | Un-transactioned `updateOrCreate` loops; duplicate inserts | no txn, no lock, non-unique index | Wrap in txn + unique constraints |
| H12 | Readiness read-consistency | Airworthiness evaluated mid-cascade reads a half-propagated tree | plain `pluck`, no isolation | Snapshot readings once (shared-lock read) before classifying |

---

## 1. Counter & Penalty Engine

### Entities & data
- **`counter_refs`** (`CounterRef`) — the counter *definition/template*. `code` (opaque auto `CTR-####`), `counter_code` (user-facing unique mnemonic, e.g. TSN/CSN), `measure_unit`, `incr_decr` (1=increase, 2=decrease), `allow_incr_decr` (0/1 enable), `min_value`/`max_value`/`initial_value` (**varchar**), `propagation_flag`, `used_for_residual_calc`, `orange_light_limit` (default 90), `parent_counter` (linked-measure parent by `counter_code`), `propagation_from_parent`. `getNameAttribute()` aliases `->name`→`counter_code`.
- **`functional_location_counters`** (`FunctionalLocationCounter`) — aircraft-level reading. `value_dec` `decimal(15,4)` **cast to `'float'`**, `value_hhmm`, `reading_date`/`reading_hour`, `max_dec`/`max_hhmm`, `remaining`/`residual` (**varchar**), `propagate`, `is_used`, `info_source`. **`unique(functional_location_id, counter_ref_id)`**. Computed `getToneAttribute()` (grey/green/amber/red vs max & `orange_light_limit`).
- **`equipment_counters`** (`EquipmentCounter`) — same shape on `equipment_id`; `unique(equipment_id, counter_ref_id)`; `remaining` written by the Inventory projection.
- **`counter_history`** (`CounterHistory`) — append-only audit, **polymorphic** string `subject_type` (`functional_location`/`equipment`, normalized via `CounterSubject::normalize`) + `subject_id`. prev/new `value_dec`+`value_hhmm`, `delta_dec`, reading date/hour, `info_source`, `source_type` (`manual`/`manual_penalty`/`event_ingest`/`penalty_cascade`/`propagated`/`correction_approved`/`recalculation`), `source_ref`, `user_id`, `note`.
- **`penalties`** (`Penalty`) — named catalog keyed on `code`; `scopeActive()`; `hasMany(PenaltyRule)`.
- **`penalty_rules`** (`PenaltyRule`) — formula rows. Canonical scope `aircraft_type_id` (**NOT NULL**); legacy polymorphic `subject_type`/`subject_id` nullable + **unread**; `aircraft_id` nullable instance-override slot (**reserved, unused**). `monitoring_counter_ref_id` (output), `rate_value`+`rate_counter_ref_id`, `static_value`+`static_counter_ref_id`, `is_relative`, `threshold_value` (nullable ATP extension), `target_item_id` (null→fire on subject; set→walk installed base for matching `item_id`), `is_active`.
- **`aircraft_type_counters`** (`AircraftTypeCounter`) — `booted::created` materializes a `FunctionalLocationCounter` (`firstOrCreate`, `propagate=true`, `is_used=false`, seeded from `initial_value`) on every FL of that type; `deleted` does **not** cascade (readings precious).
- Supporting: `counter_event_ingestions` (idempotency ledger, `idempotency_key` unique), `counter_corrections` + `counter_correction_reason_codes`, `counter_recalculation_runs` (late-event queue), `counter_compliance_evidences` (immutable). `equipments` carry `functional_location_id` + self-FK `parent_equipment_id` → the L1→L2→L3 installed-base tree.

### Business rules
- **3-tier counter model.** *Logic:* `CounterRef` = template; readings live in `functional_location_counters` (FL) **or** `equipment_counters` (Component), unique per `(subject, counter_ref_id)`; no `functional_location_types` layer. *Source:* `FunctionalLocationCounter`, `EquipmentCounter`, `AircraftTypeCounter::booted`.
- **Single counter write path.** *Logic:* five writers — `FunctionalLocationCounterUpdater::applyRows` (UI grid), `CounterEventIngestionService::ingest` (API), `CounterCorrectionService::applyApprovedCorrection`, plus the `PenaltyEngine` and `CounterPropagationEngine` cascades — all share `CounterWriteGuard` and write a `CounterHistory` row. *Source:* `FunctionalLocationCounterUpdater::applyRows`.
- **Direction guard.** *Logic:* `allow_incr_decr===1` enables; `incr_decr` (1=up-only, 2=down-only) is the locked direction. Violation only when toggle on, prev & new known, value changed, wrong-way. UI path **defers** the row + records `directionViolations` for a confirm dialog (re-run with `allowDirectionOverride=true`); API path **hard-rejects** (`rejected_direction`) unless payload sets `allow_direction_override`; correction path **skips** the guard (authorized override). *Source:* `CounterWriteGuard::violatesDirection` + the three writers.
- **Reset-to-null.** *Logic:* per-row `reset_to_null` forces both value columns NULL, `is_used=false`, bypasses guard/clamp/cascades, still writes history note `Reset to Null`. *Source:* `applyRows`.
- **Min/max clamp.** *Logic:* `clamp(value, ref)` clamps decimals to `min_value`/`max_value`; null value/ref/bound pass through. **HH:MM-native values are NOT clamped** (standing TODO). *Source:* `CounterWriteGuard::clamp`.
- **`is_used` reflects resolved value** (genuine `0` marks used). *Source:* `applyRows`.
- **Linked-measure remaining + residual flag.** *Logic:* `remaining(max,own,parent,precision,usedForResidualCalc)` → null if `max` null or residual opted out; effective = `max(own,parent)` when both, else own, else parent; `round(max−effective)`. Parent applies only when CounterRef has `parent_counter` AND `propagation_from_parent===1`. Residual flag treated ON for null/unseeded; only explicit `0` excludes. *Source:* `CounterRemainingCalculator::remaining`.
- **Weststar penalty formula.** *Logic:* `increment = round(rate_value×Δrate + (static_value+Δstatic), 4)`; Δ's are this event's deltas (0 if absent); `is_relative` makes the static operand the monitoring counter itself. `newMon = round(prevMon + increment, 4)`, clamped; the **post-clamp** applied increment drives history/event/recursion (zero ⇒ no-op). Evaluated at the **event** level via `applyForEvent(subjectType,subjectId,[refId⇒delta],selectedIds,sourceRef)`; `cascade()` is the single-counter shim. *Source:* `PenaltyEngine::applyRule`.
- **Threshold gating (ATP extension).** *Logic:* with `threshold_value` set, the **rate term applies every time** above threshold, the **static term only on the crossing edge** (`prev < threshold`). *Source:* `PenaltyEngine::applyThreshold`.
- **Forward-only, positive deltas only.** *Logic:* `positiveDeltas()` keeps `delta>0`; stateless w.r.t. fire history. *Source:* `PenaltyEngine::positiveDeltas`.
- **Aircraft-type scoped + nullable override.** *Logic:* resolve subject `aircraft_type_id` (FL: `aircraft_types.code == functional_locations.type`; Equipment: walk `parent_equipment_id` up, max 20 hops), query active rules for that type; unresolved type ⇒ **no rules fire**. `aircraft_id` read by nothing. *Source:* `PenaltyEngine::evaluate`, `resolveAircraftTypeId`.
- **Target resolution.** *Logic:* `target_item_id` null ⇒ age monitoring counter on the subject; set ⇒ walk `allInstalledEquipment()` for the Component with matching `item_id`. *Source:* `PenaltyEngine::resolveTarget`.
- **Edge-detect / fire-once / MAX_DEPTH.** *Logic:* `(rule.id:target.type:target.id)` in `$seen` prevents double-ageing in one cascade; recurse with `[monitoringRefId⇒appliedIncrement]`; bounded at `MAX_DEPTH=5`. *Source:* `PenaltyEngine::applyRule`/`evaluate`.
- **Manual penalty is a direct delta** (`source_type='manual_penalty'`), NOT the formula — deliberately, to avoid double-counting with `applyRows`. *Source:* `applyManualPenalties`.
- **Propagation cascade.** *Logic:* in `applyRows`, when `delta≠0` + per-row `propagate` true + `counter_ref_id` set, replay the **same delta** on every `EquipmentCounter` matched **by `counter_ref_id` only** across `allInstalledEquipment()`; CounterRef `propagation_flag===0` suppresses; children inherit reading date/hour/source, clamped, history `source_type='propagated'`. *Source:* `CounterPropagationEngine::propagateFromFl`.
- **`CounterUpdated` from derived cascades + fan-out.** *Logic:* dispatched by every write path incl. both cascades; `ShouldDispatchAfterCommit`; `CounterModuleIntegrationService::syncAfterCounterUpdated` routes FL ⇒ `daily_flight_logs` + `WorkPackageTask.remaining_fh/_fc` (+status), Equipment ⇒ `equipment_counters.remaining`, always writes `counter_compliance_evidences`. *Source:* `CounterModuleIntegrationService`.
- **API ingestion** is idempotent; resolves/creates the row; derives value from absolute / HH:MM / `prev+delta`; guard (hard reject) + clamp; **fires penalties only when `appliedDelta>0` AND `selected_penalty_ids` non-empty**, and **does NOT propagate** down the tree. *Source:* `CounterEventIngestionService::ingest`.
- **Approved corrections** clamp only (no direction guard); safety-critical codes (`config('counters.safety_critical_codes')`) require approval; history `source_type='correction_approved'`. *Source:* `CounterCorrectionService`.
- **Late-event recalc queue.** *Logic:* late when `prevReadingDate!==null && readingDate<prevReadingDate` (**string compare, TZ-naive**); enqueue `CounterRecalculationRun`; `counters:process-recalculations` (every 5 min, `withoutOverlapping`) replays the latest history row by `reading_date` then `id`, **FL subjects only**. *Source:* `ingest`, `ProcessCounterRecalculations`, `CounterRecalculationService::recompute`.

### Concurrency (see H1–H6) & improvements
Detailed per-site analysis above (H1–H6). HOW-only improvements (rules preserved): `decimal:4`/value-object instead of `float` cast on `decimal(15,4)`; type `remaining`/`residual` as decimal not varchar; one `CounterValue` normalizer (closes the unclamped-HH:MM TODO — **behavior change, ASK C1**); `CounterSubject` enum end-to-end; collapse the duplicate `resolveAircraftId`. **ASK** items → §6.

---

## 2. Flight / Daily-Update Pipeline

### Entities & data
- **`Flight`/`flights`** — per-flight leg. Deltas `total_flight_real_minutes`, `airframe_real_minutes` (`decimal:2`); aircraft keys `registration`/`fl_code`/`serial_number`/`aircraft_type`; `scheduled_date` (reading date); `status ∈ {Scheduled, Released, Completed, Cancelled}` (plain string, no machine). **No FK to `functional_locations`** — matched by string code at handover.
- **`DailyFlightLog`/`daily_flight_logs`** — per-aircraft-per-day aggregator behind the "Technical Log" L2 page (naming clash with the unrelated `TechnicalLog` MEL entity). **Absolute after-flight readings**: `ac_hours_after_minutes` (`decimal:2`), `ac_cycle_after` (int), `*_before`/`*_daily` operands; `log_date`.
- **`CounterEventIngestion`** — idempotency + audit row per event. **Live counters** are the authoritative target (FL subjects only for flight). `CounterRef` codes resolved from `config/counters.php` (defaults `FH/.../APUH` hours, `FC/.../LGC` cycles).

### Business rules
- **Saving a Technical Log IS the "daily update."** *Logic:* `save()` runs `normalizeCounterTotals()` (auto-derives `*_after = *_before + *_daily` when both operands present, **overriding** a typed after-value), validates, then in a `DB::transaction` persists + hands absolute readings to the handover service with the actor + `selectedPenaltyIds`. *Source:* `TechnicalLogPage::save`/`normalizeCounterTotals`.
- **Saving Flight Details hands the single-flight duration as a delta.** *Logic:* handover passes `total_flight_real_minutes` as `delta_dec`; skips if null or ≤0. *Source:* `FlightDetailsPage::save` → `FlightDetailsCounterHandoverService::handover`.
- **Technical-Log handover captures absolute hours+cycles**, one event per configured code; null source value / missing ref skipped; aircraft resolved fuzzy (exact, cross-field, `LIKE`, registration-exact first). *Source:* `TechnicalLogCounterHandoverService`.
- **Flight-Details handover ages one monitoring counter by duration**; aircraft resolved **exact-match only**. *Source:* `FlightDetailsCounterHandoverService`.
- **All flight readings reach counters only via `CounterEventIngestionService::ingest`** (locks the row via `lockOrCreate`, computes value, guard+clamp, history `source_type='event_ingest'`, dispatches `CounterUpdated`). *Source:* `ingest`, `CounterModuleIntegrationService`.
- **Idempotency (absolute path safe).** *Logic:* key = `sha1('technical-log-handover'|log->id|counterRefId|number_format(value,4)|readingDate)`; `ingest` short-circuits `duplicate` when same key already `processed_at`. Keyed on **absolute** value ⇒ re-save reuses the key. *Verified:* `TechnicalLogPageTest::test_repeated_identical_save_…`.
- **Idempotency (delta path — collision hazard, KEEP behavior).** *Logic:* key from the per-flight **delta**; re-save of the same flight deduped, but **editing the duration re-keys and applies an additional delta without reversing the prior one** (over-counts). *Source:* `FlightDetailsCounterHandoverService::idempotencyKey`. → **ASK F1.**
- **Penalties fire on a flight only when explicitly selected.** *Logic:* cascade runs only if `appliedDelta>0` AND `selected_penalty_ids` non-empty; empty selection ages the base counter, fires zero rules; ids sanitized + `exists:penalties,id`. *Source:* `ingest`, `*HandoverService::sanitizeSelectedPenaltyIds`. → **ASK F6.**
- **Backdated reading applied but flagged late + queued** (see §1 late-event). **Out-of-direction flight readings hard-reject** (handover never sets `allow_direction_override`). **Unresolvable aircraft silently dropped** (logged, save still "succeeds"). *Source:* `ingest`, `*HandoverService::handover`. → **ASK F4.**

### State transitions
- Ingestion lifecycle (the real state machine): `pending` → `processed` / `processed_late_event` / `processed_direction_override` / `rejected_direction` / `ignored_missing_subject` / `ignored_no_value`; or `duplicate`. `Flight.status` is a free `in:`-validated string with no transition guards and no effect on handover.

### Concurrency (H1, H4) & improvements
Lost-update on concurrent same-aircraft saves (H1); double-ingest window because the idempotency check is a non-locked read (H4 — add the UNIQUE-backed insert-or-catch). HOW-only: collapse the two near-duplicate handover services into one with an absolute/delta strategy; share aircraft resolution with the rest of Fleet; surface the `{applied,duplicate,skipped}` result to the user (today an unmatched-aircraft save looks successful but writes no counter). **ASK** F1–F6 → §6.

---

## 3. Readiness / Airworthiness Auditing

### Entities & data
- **`FunctionalLocation`** — `registration` (preferred) / `code` (fallback) resolve a tail; `type` = aircraft-type code; relations `counters()`, `maintenancePrograms()` (pivot w/ `date_assigned`/`date_unassigned`), `configurationVariants()`, `allInstalledEquipment()`.
- **`MaintenanceProgram` → `MaintenanceProgramItem`** (`item_type` Visit|Task, `apply_one_time`, `link_to_component`) **→ `MaintenanceProgramItemCounter`** (`counter_ref_id`, `threshold`, `interval`, `alarm`, `is_relative`).
- **`MaintenanceProgramCompliance`** — per-aircraft last-performed: `reading_at_completion` (counter anchor), `completed_date` (calendar anchor), `work_reference`. **Non-unique** index `mpc_fl_item_counter_idx`.
- **`PublicationCompliance`** — per-tail embodiment; `compliance_status` ∈ `STATUSES=[Embodied,Open,Pending,Removed,Waived]`; `OUTSTANDING=[Open,Pending]`; `SATISFIED=[Embodied,Waived]`; `utilization_snapshot` JSON.
- **`TechnicalPublication`** — `reference`, `status` ∈ `[Draft,Applicable,Not Applicable,Superseded]`, `applicable_aircraft_type` (null ⇒ fleet-wide), `belongsTo PublicationType` (AD/SB code on `publication_types.code`).
- **`ApplicableConfiguration` → `ApplicableConfigurationItem`** (self-ref `parent_id` tree, `ata_code`, `allowable_part_number`, `expected_quantity`, `requirement_type`); **`ConfigurationVariant`** belongsTo one config, belongsToMany FL.
- **`UtilizationModel` → `UtilizationModelRate`** (monthly accrual jan..dec by `measure_unit_code`).

### Business rules
- **Five criteria, each PASS / FAIL / NOT EVALUATED, as-of a date.** *Logic:* `getReview($registration,$asOf=null)` resolves FL by registration OR code, evaluates Work Packages, AMP, Technical Publications, Defects, Configuration Control. *Source:* `AirworthinessReviewService::getReview`.
- **As-of defaults to now, never throws**; only Defects actually consumes the as-of instant. *Source:* `parseAsOf`, `defectsCriterion`.
- **C1 Work Packages.** Count `WorkPackage` for the registration with `status NOT IN {Completed,Closed,Cancelled}`; 0⇒PASS else FAIL; never NOT EVALUATED. *Source:* `workPackagesCriterion`.
- **C2 AMP.** FL null ⇒ NOT EVALUATED; no Approved+assigned programme ⇒ NOT EVALUATED; else `overdue===0`⇒PASS, any overdue⇒FAIL. **Due Soon does not fail.** *Source:* `ampCriterion`. → ASK R3.
- **C3 Technical Publications (AD only).** Applicable mandatory = pubs with type `code='AD'`, `status='Applicable'`, `applicable_aircraft_type` null-or-equal to FL type. Empty ⇒ NOT EVALUATED (the explicit never-fake-green case). Outstanding = applicable.diff(satisfied=`{Embodied,Waived}`); 0⇒PASS else FAIL. **Only AD-type checked despite the AD/SB label.** *Source:* `technicalPublicationsCriterion`. → ASK R1.
- **C4 Defects.** `open` = status Open; `overdueDeferred` = Deferred AND `mel_expiry_date < asOf`; `bad=open+overdueDeferred`; 0⇒PASS else FAIL. Future-expiry deferred does not fail. *Source:* `defectsCriterion`.
- **C5 Configuration Control.** FL null or no assigned variant/config ⇒ NOT EVALUATED; else `mismatch===0`⇒PASS else FAIL. *Source:* `configurationCriterion` → `ConfigurationComparisonService::statusForLocation`.
- **Overall verdict (conservative, never fake-green).** *Logic:* **any FAIL ⇒ NOT AIRWORTHY**; else **any NOT EVALUATED ⇒ REVIEW INCOMPLETE**; else (all evaluated + pass) ⇒ **AIRWORTHY**. FAIL outranks NOT EVALUATED. Computed-on-read; not persisted. *Source:* `getReview` match block.
- **Maintenance-due classification.** *Logic:* per item-counter with non-null `threshold` and a live FL reading, `remaining = nextDue − reading`; `<0`⇒Overdue; `<=alarm`⇒Due Soon (alarm defaults 0.0 when null); else OK. Null threshold / no reading ⇒ skipped. *Source:* `MaintenanceDueService::dueItemsForLocation`. → ASK R4.
- **Threshold-first vs interval-repeat forecasting.** *Logic:* no completion ⇒ `nextDue=threshold` (`source='threshold'`); completion + `interval>0` ⇒ `nextDue=reading_at_completion+interval` (`source='interval'`); completion + `apply_one_time` ⇒ satisfied/skipped. Keys off `interval`, **not** the counter's `is_relative`. Calendar-interval not implemented. *Source:* `dueItemsForLocation`, `latestCompletions`. → ASK R6.
- **Config classification.** Expected = applicable items with non-empty PN, grouped by PN, `expectedQty=Σ max(1,expected_quantity)`; installed = `allInstalledEquipment()` grouped by `item.code`; `installedQty>=expectedQty`⇒In Sync, `==0`⇒Missing, else Missing Qty; `mismatch=Missing+Missing Qty`. *Source:* `ConfigurationComparisonService::classify`.
- **Utilization → next-due date.** *Logic:* `dailyRate` = avg of 12 monthly `UtilizationModelRate` values for the matched `measure_unit_code` (Minutes ÷60 to hours) ÷ `DAYS_PER_MONTH=30.4375`; null if any link missing or rate≤0; `nextDueDate=(from??now)->addDays(ceil(remaining/dailyRate))`. *Source:* `UtilizationForecastService`.

### State transitions
- `PublicationCompliance.compliance_status` is a plain string (no enforced graph). Satisfied = `{Embodied,Waived}`; outstanding = `{Open,Pending}`; **`Removed` is treated as outstanding** (only `{Embodied,Waived}` satisfy) → re-fails airworthiness. The recording UI writes only `Embodied`/`Removed`; `Open`/`Pending` come from seed/import. → ASK R2.
- AMP recurrence is data-driven (satisfied / recurs / pinned-at-threshold). Verdict is recomputed on read, not stored.

### Concurrency (H11, H12) & improvements
Two un-transactioned, unlocked `updateOrCreate` compliance writers (H11); read-consistency during cascades (H12). Null-safe reviewer (`auth()->user()?->name ?? 'System'` — currently fatal on null actor). HOW-only: unique constraints on the compliance keys; centralise magic status strings into enums (`PublicationCompliance::*_STATUSES` already exists); query `UtilizationModel`/FL by registration instead of full-table maps. **ASK** R1–R6 → §6.

---

## 4. MRO Workflows

### Entities & data
- **`WorkPackage`** — `code` (unique `WP-###`), `work_package_type` (default `Base Maintenance`), `aircraft_registration`, `status` (string, default `Planned`, indexed), `progress_percent`. Appends immutable `work_package_activity_log`.
- **`WorkPackageTask`** — FK `work_package_id` (cascade), `reference` (=AMP item code), `component` (=counter code), `remaining_fh/_fc`, `status` (default `OK`, indexed), `work_order_code`. **No unique constraints.**
- **`WorkOrder`** — `code` (unique `WO-#####`), `status_code` (string, nullable, no default, → `mro_status_objects.code`), `work_package_code` (string FK by value). WO status magic codes: `-0000001`=Planned, `-0000002`=Released, `00000003`=Closed, `-0000003`=Cancelled, `-0000019`=Postponed.
- **`TimeSheet`** — `code` (unique `TS-YY-###`), FK `work_order_id`+`employee_id` (cascade; **`'integer'` cast load-bearing** — deployed MySQL hydrates FK as strings → prod-only TypeError, QA-MRO-01), `period_*` (NOT NULL, auto-derived), `status` (default `Draft`); submit/approve/reject columns **retained but unused**. **`TimeSheetEntry`**: `start_at` NOT NULL, `end_at` nullable (open session), `duration_minutes`.
- **`Defect`** — `code` (unique `D-XXXXXXX`), `defect_status` (Draft/Open/Deferred/Closed), `deferred` bool, `mel_*`, `closed_date/_time`, `part_on/off` (JSON). `ACTIVE_STATUSES=['Open','Deferred']`. **`DefectCounter`**: FK cascade, at_defect/at_closure values.
- **`MaintenanceProgramCompliance`** — see §3; **non-unique** composite index only.
- **Dead reference:** `mro_status_objects`, `workflow_groups` — canonical lookups **queried by nothing** (codes hard-coded).

### Business rules
- **WP built from due items selected on Maintenance Planning.** *Logic:* `MaintenancePlanningPage::buildWorkPackage()` filters checked rows, `session()->put('maintenance.wp_selection', …)`, redirects; `WorkPackagePage::build()` validates (≥1 item, title, type, date), creates one `WorkPackage` + one `WorkPackageTask` per item (`reference`/`component` from the row), forgets the session, redirects to `mro.work-package.show`. *Source:* `MaintenancePlanningPage::buildWorkPackage`, `WorkPackagePage::build`.
- **Task round-trip key contract.** *Logic:* due item carries `item=$item->code`, `counter=counter_code`; builder writes them to `task.reference`/`task.component`; recorder matches `item->code===task->reference` AND `counter_code===task->component`. **Value-equal string join — a rename silently writes 0 rows.** *Source:* `MaintenanceDueService`, `MaintenanceCompletionRecorder`.
- **Code generation.** WP `NextCode::sequential(WorkPackage,'WP-',3)`; WO `sequential(WorkOrder,'WO-',5,first:30000)`; TS `sequential(TimeSheet,'TS-'.now('y').'-',3)`; Defect `randomToken(Defect,'D-',7)`. *Source:* model/page `nextCode` methods.
- **NextCode sequential (#394 history).** *Logic:* scans only strictly-numeric `<prefix><digits>` codes, takes numeric max (or `first-1`), `exists()`-probes forward. Fixed the old lexicographic-max collision 500. Still **read-then-write, no lock**. *Source:* `Support\NextCode::sequential`. → H7.
- **Defect validation.** *Logic:* require reg/type/status/date/short_title; when `deferred` ⇒ `mel_category` (A/B/C/D/CF) + `mel_expiry_date` required; when Closed ⇒ `closed_date` required. *Source:* `DefectsPage::rules`.
- **Defect counter re-sync is destructive** (`counters()->delete()` then recreate — IDs/timestamps lost). Person/part-number/ATA fields **display-only, not persisted**. *Source:* `DefectsPage::syncCounters`/`draftToColumns`. → ASK M5.
- **Time Sheet.** *Logic:* `derivePeriod()`=min(starts)/max(ends) (keeps NOT-NULL period meaningful); `syncEntries()` computes `duration_minutes=round((end−start)/60)`, null `end_at`=open; FK casts to `'integer'`. *Source:* `TimeSheetFormPage`, `TimeSheet::$casts`.
- **Record Completion writes `MaintenanceProgramCompliance`.** *Logic:* WP must be saved; resolve FL by registration/code; load FL's **Approved + still-assigned** programmes (`wherePivotNull('date_unassigned')`); snapshot **live FL reading** (`value_dec` by `counter_ref_id`); per task×item match `item->code===task->reference`, per counter `counter_code===task->component`; `updateOrCreate` on `(fl_id,item_id,counter_ref_id)` writing `reading_at_completion`, `completed_date`, `work_reference=wp->code`. *Source:* `MaintenanceCompletionRecorder::recordForWorkPackage`. → H9, ASK M4.
- **Completion closes the due loop (consumed, not recomputed).** `latestCompletions()` reads per `{itemId}:{counterRefId}` (last wins) → `next-due=reading+interval`; `apply_one_time` satisfied. No write-back to the task. *Source:* `MaintenanceDueService`.
- **All saves: Eloquent + `DB::transaction` + `AuditUserObserver`**; child tables sync via diff-and-prune (`whereNotIn($keepIds)->delete()`); WO↔WP by `work_orders.work_package_code` value, not a junction. *Source:* `WorkPackageFormPage::save`/`syncTasks`/`syncWorkOrderLinks`.

### State transitions
- **WP status** — no machine; free `<select>` validated against an enum. **CROSS-L1 ENUM DRIFT (verified):** Fleet builder writes `status='Draft'` + tasks `'Pending'`, both **outside** the MRO enums (`Planned/In Progress/Completed/Cancelled` and `OK/Due Soon/Overdue`), so the first MRO save fails validation until corrected. *Source:* `WorkPackagePage::build` vs `WorkPackageFormPage::rules`. → ASK M1.
- **WP Task status** — builder `Pending`, default `OK`, MRO enum `OK/Due Soon/Overdue`.
- **Work Order** — magic codes, validated `in:…`; `isClosed()≡00000003`, `isCancelled()≡-0000003`. **No transition rules, no date side-effects** (Closed doesn't stamp `closed_date`). `MroStatusObject` unused. → ASK M2.
- **Time Sheet** — workflow columns retained but **UI removed**; always saves `status ?: 'Draft'`; `isEditable()` is a dead guard. → ASK M3.
- **Defect** — `Draft→Open→Deferred→Closed`, unordered free select; `deferred` bool orthogonal to status; feeds `AirworthinessReviewService`.

### Concurrency (H7–H11) & improvements
Code-gen collisions (H7), double WP build (H8), double Record-Completion on a non-unique index (H9), last-write-wins on all saves (H10), un-transactioned compliance writer (H11). HOW-only: generate codes inside the insert txn with a locked sequence + catch-unique-retry; unique index on the compliance tuple; add real FKs alongside the string joins (removes the recorder's N+1 FL map); unify WO/WP status via enum or the unused `mro_status_objects`; optimistic locking; non-destructive defect-counter sync. **ASK** M1–M5 → §6.

---

## 5. Cross-module data flow (summary)

```
Flight save / Tech-Log save / API ingest / UI grid / Correction
        │ (all via CounterWriteGuard, FOR UPDATE in txn)
        ▼
  counter row + CounterHistory ──► PenaltyEngine ─┐
        │                          CounterPropagation (L1→L2→L3) ─┤
        ▼                                                          ▼
  CounterUpdated (after-commit) ──► CounterModuleIntegrationService
        ├─► Flight Recording: daily_flight_logs
        ├─► MRO: work_package_tasks.remaining_fh/_fc + status
        ├─► Inventory: equipment_counters.remaining
        └─► Compliance: counter_compliance_evidences

Maintenance Planning ──(session pick)──► WP builder ──► WorkPackage + Tasks ──► MRO execution ──► Record Completion ──► MaintenanceProgramCompliance ──► (closes AMP due loop)

Airworthiness = f(open WPs, AMP overdue, outstanding AD pubs, open/MEL-expired defects, config mismatch), recomputed on read.
```

---

## 6. Open questions for the user — decide before Phase 5 (ASK)

These would change **observable behavior or a business rule**; I will not change them without sign-off.

### Resolved 2026-06-18
- **C5** → add optimistic `lock_version` columns (HOW-improvement).
- **M4** → overwrite one row per `(fl,item,counter)` + UNIQUE constraint (faithful).
- **F1/F2** → Flight Details moves to **absolute readings** (approved behavior change; `improvements.md` #6).
- **M1** → MRO status enum is canonical; Fleet builder emits MRO values.
- **C2** → default **once-per-event** (faithful, no `penalty_fire_records` table) unless a hard once-ever guarantee is requested.

Still open: **C3/F5, C4/F3, C6, F4, F6, R1–R6, M2, M3, M5** — none block Phase 3 schema; they surface again in their own phases.

**Counter / penalty**
- **C1.** Clamp HH:MM-native readings to min/max like decimals? (Today HH:MM is unclamped.)
- **C2.** Penalty static-term: fire **once ever** per threshold crossing, or **once per event**? (If once-ever, needs a persisted fire-record, not just locking.)
- **C3 / F5.** Should the API/flight ingestion path **auto-fire type penalties and propagate down the tree** like the UI grid, or stay intentionally narrower?
- **C4 / F3.** Late-event day boundary: **UTC or aircraft-local**? (Today: TZ-naive string compare; `event_at_utc`+`event_timezone` are captured but unused.)
- **C5.** With five write paths and last-write-wins, which path is **authoritative**, and what's the conflict rule? (Drives whether v2 adds an optimistic `version` column.)
- **C6.** Recalc: value-reconciliation only, or also **replay penalty/propagation cascades** and refresh equipment Inventory?

**Flight**
- **F1.** Editing a Flight Details duration should **reverse the prior delta** before applying the new one? (Today it additively over-counts.)
- **F2.** Should Flight Details capture **absolute readings** (like Technical Log) so both share one idempotency/correction model?
- **F4.** Unresolvable aircraft on a flight: **hard validation error** or keep the silent skip?
- **F6.** Confirm "penalties fire only when explicitly selected per flight" — or should some type rules fire automatically every flight?

**Readiness / airworthiness**
- **R1.** Tech-Pubs criterion: **AD-only** (current) or also SB/EOAS/other mandatory types?
- **R2.** A **`Removed`** publication re-fails airworthiness (only Embodied/Waived satisfy) — intended, or should Removed be neutral?
- **R3.** Confirm **Due Soon** never affects the verdict (only Overdue fails AMP).
- **R4.** A null `alarm` ⇒ Due Soon only at exactly `remaining==0` — intended "no early warning", or a default margin?
- **R5.** Should WP/AMP/Pubs/Config also be **time-rewound** for a true historical as-of, or is as-of Defects-only acceptable?
- **R6.** Is **calendar-interval** (date-driven) maintenance recurrence in scope for v2? (Today only counter-interval.)

**MRO**
- **M1.** Reconcile WP/Task status vocabularies (builder `Draft`/`Pending` vs MRO `Planned/In Progress/Completed/Cancelled` & `OK/Due Soon/Overdue`) — adopt builder values into the enum, or have the builder emit MRO values?
- **M2.** Enforce a **Work Order state machine** with date side-effects (stamp released/closed)? Columns exist, never auto-set.
- **M3.** Reinstate **Time Sheet approval** (Draft→Submitted→Approved/Rejected with authz), or stay workflow-less?
- **M4.** Completion grain: **overwrite** one row per `(FL,item,counter)` (→ unique index) or **append-only** event ledger (→ immutable audit)?
- **M5.** Which **display-only Defect fields** (Raised By, Performed By, Part ON/OFF, ATA) must persist in v2, and against which reference sources?
