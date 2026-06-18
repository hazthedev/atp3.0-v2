# ATP 3.0 v2 — Improvements Log

Structural improvements made in v2 over the old ATP 3.0 design. Each entry: the old
approach, what changed, why it's better. **Business rules are preserved exactly** —
these are HOW changes, not WHAT changes. Anything that would change observable
behavior is NOT logged here as a done change; it goes to the user as a question first.

---

## 1. Database engine: SQLite → MySQL, with real (and tested) concurrency control
**Status:** decided in Phase 1; concurrency guards land + are proven in Phase 5.

- **Old approach:** atp3.0 ran on **SQLite** locally (`DB_CONNECTION=sqlite`,
  `database/database.sqlite`). SQLite serialises all writes (one writer at a time),
  so the app's concurrency guards were effectively **silent no-ops** — they never had
  to fend off a real concurrent writer — and the MySQL proof test for them was
  **skipped in CI**. Races that exist on MySQL were invisible in dev and untested.
- **What changed:** v2 targets **MySQL** from the first migration
  (`DB_CONNECTION=mysql`, database `atp3_v2`). Every concurrency-sensitive path
  flagged in Phase 2's domain spec will get **real** protection (row locks inside
  transactions / unique constraints / version columns as appropriate) and a test
  that **proves the guard against MySQL** — not SQLite, not skipped.
- **Why it's better:** the production engine is MySQL with true multi-writer
  concurrency; the guards must hold there. Testing on the real engine is the only
  way to know they do. This removes the single most dangerous piece of inherited
  technical debt before any logic is ported.

---

## 2. Real numeric types instead of `float` / `varchar` (Phase 3 schema draft)
- **Old:** counter `value_dec` etc. were `DECIMAL(15,4)` but **cast to `float`** in the model (reintroducing IEEE-754 error the scale was chosen to prevent — the engine sprinkles defensive `round(…,4)` to compensate); `remaining`/`residual` stored as **`varchar`**.
- **Changed:** v2 columns are `DECIMAL(15,4)` and consumed as fixed-precision; `remaining`/`residual` are `DECIMAL(15,4) NULL`.
- **Why:** removes the rounding the scale was meant to avoid and lets the DB compare/sort numerically. No behavioral change at 4dp (rule preserved).

## 3. Referential integrity by FK, not value-equal string matching (Phase 3 schema draft)
- **Old:** WP↔WO joined by `work_package_code` string; task↔AMP-item by `reference == code`; task↔counter by `component == counter_code`; nearly everything↔aircraft by `registration` string (no FK). A rename silently wrote 0 rows.
- **Changed:** fresh schema uses real FKs (`work_orders.work_package_id`, `work_package_tasks.maintenance_program_item_id` + `counter_ref_id`, `*.functional_location_id`). The mapping **rule** is identical; a denormalised display string can ride alongside.
- **Why:** referential integrity, removes the recorder's N+1 FL lookup map, converts a silent-0-rows failure into an enforced constraint. (Note: changes a *failure mode*, not a business rule — flagged for awareness, not a WHAT-change.)

## 4. Concurrency enforced in the schema + proven on MySQL (Phase 3 schema draft)
- **Old:** races guarded (if at all) only in app code; UNIQUE/lock gaps behind `updateOrCreate` and code-gen (H4/H7/H9/H11); no optimistic versioning.
- **Changed:** UNIQUE constraints where races exist (idempotency key, publication compliance, completion tuple per fork M4); optimistic `lock_version` on hot mutable rows (fork C5); see `domain-spec.md` §0 H1–H12. All proven against MySQL in CI (entry #1).
- **Why:** turns "the unique constraint becomes a 500 race" into deterministic insert-or-catch, and makes lost-update detectable rather than silent.

## 5. Enum-backed statuses + `CounterSubject` enum (Phase 3 schema draft)
- **Old:** WP/WO/Task/Defect statuses were free strings (magic codes like `-0000001`); `subject_type` flowed as ~17 string literals; the cross-L1 enum drift made Fleet-built WPs fail the first MRO save.
- **Changed:** DB-enforced enums for statuses (vocabulary per fork M1) and a real `CounterSubject` enum end-to-end.
- **Why:** removes stringly-typed drift; one source of truth for allowed values. (Allowed *values* unchanged — rule preserved; vocabulary reconciliation is fork M1.)

## 6. Flight Details capture: per-flight delta → absolute readings (APPROVED behavior change)
**Status:** user sign-off 2026-06-18 (fork F1/F2). This is the one entry here that **does** change observable behavior — explicitly approved, not silent.

- **Old approach:** `FlightDetailsCounterHandoverService` handed a per-flight **delta** (`total_flight_real_minutes`) to the counter engine. The idempotency key was built from the delta + `flight_id`, so **editing a flight's duration re-keyed and applied a second delta without reversing the first** — silently over-counting the monitoring counter.
- **What changed:** Flight Details captures **absolute** after-flight readings (like the Technical Log path); a correction **reverses-and-replaces** rather than additively re-ingesting; both flight paths share one idempotency/correction model.
- **Why it's better:** eliminates the over-count-on-edit bug, unifies the two flight handover paths, and makes flight corrections behave like every other counter correction. **Business rule preserved:** a flight still ages the same counter by the same flown amount — only the capture/correction mechanism changes.

## 7. Explicit short FK/index names (MySQL 64-char identifier limit) — caught by running on MySQL
**Status:** found + fixed when migrations first ran on MySQL (the moment entry #1 paid off).

- **Old approach:** relied on Laravel's auto-generated FK/index names. On long table+column combos (e.g. `maintenance_program_item_counters_maintenance_program_item_id_foreign`) these exceed MySQL's **64-char identifier limit**. SQLite doesn't enforce it, so the SQLite smoke-validation passed while MySQL rejected the migration with error 1059 — the *exact* class of bug the reference repo hit ("shorten Maintenance Program index/FK names for MySQL").
- **What changed:** explicit short constraint names (`mpic_*`, `mpfl_*`, `mpc_*`, `aci_*`, `cvfl_*`) via `constrained(indexName: …)` and named `index()`/`unique()` on the long-named tables.
- **Why it's better:** migrations now run clean on MySQL 8.0 (verified). This is the concrete proof that validating on the production engine — not SQLite — catches real bugs SQLite hides (reinforces entry #1).

## 8. MySQL verification complete (Phases 1 & 5 closed on the real engine)
**Status:** done 2026-06-18.

- All 14 migrations run clean on **MySQL 8.0.44**; demo seed loads; every module screen renders 200 live on MySQL.
- The lost-update concurrency proof (`CounterLostUpdateMysqlTest`) **executes and passes on MySQL** (FOR UPDATE row lock → second connection hits lock-wait-timeout 1205) — the test v1 left skipped now genuinely runs. A `mysql_second` connection was added for the cross-connection contention; the test deliberately avoids `RefreshDatabase` (its per-test transaction would hide the committed row from the second connection).

<!-- Further entries appended as improvements are made during Phases 3–6. -->
