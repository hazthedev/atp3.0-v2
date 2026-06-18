# ATP 3.0 v2 — User Flows (Phase 4, DRAFT for [REVIEW])

> End-to-end journeys per module, with inter-module navigation made explicit. Where the
> old flow was confusing, dead-ended, or made the user work too hard, the v2 flow and the
> reason for the change are noted as **↑ change**. No screens built yet — this defines
> them. Pairs with `domain-spec.md` (rules) and `schema-draft.md` (data).

## Navigation model
- **Sidebar = 10 L1 modules** (Dashboard, Administration, Business Partners, Inventory, Human Resources, Technical Data, Fleet, Flight Recording, MRO, Reports). One L1 active at a time.
- **The aircraft (Functional Location) is the spine.** Most journeys start by picking an aircraft and stay in its context; cross-module jumps carry the aircraft with them (registration in the URL).
- **↑ change:** the old app had dead sidebar entries (stubs) mixed with live pages and a header aircraft picker hardcoded to one tail. v2: stubs are hidden until built; the picker lists every aircraft and every deep-link is registration-scoped.

---

## 1. Auth
Login → (authenticated) Dashboard. All routes behind the auth wall. Show-password toggle on login. No registration self-service for an enterprise tool (admin provisions users). *Single, obvious entry; nothing else reachable unauthenticated.*

## 2. Counter recording (the core loop) — Fleet › Aircraft Card
1. Pick aircraft (header picker) → **Aircraft Card** (read-only by default).
2. **Counters** tab → live readings list; **Update Counter** enters Record/Update mode.
3. Enter readings (Delta auto-computes Value, or vice-versa); per-row Propagation toggle; direction-locked counters warn-and-confirm on a wrong-way move.
4. Save → one transaction: write + history + penalty cascade + propagation (L1→L2→L3) → cross-module projections refresh (Inventory remaining, MRO task status, daily flight log).
5. **Counter History** modal per counter (clock icon) for the audit trail.
- **↑ change:** old edit mode lost input focus mid-type and had a dead Actions column; the read path and edit path are one coherent grid in v2. Direction overrides and corrections route through the same confirm UI, not separate hidden modals.

## 3. Flight / daily update — Flight Recording
1. Pick aircraft → **Technical Log** (daily) or **Flight Details** (per leg).
2. Enter the day's / flight's readings; optionally select which penalties apply to this flight.
3. Save → readings hand to the counter engine (same write path as §2); penalties fire only for the selected rules.
- **↑ change (approved, fork F1/F2):** Flight Details now captures **absolute** after-flight readings, and editing a flight **reverse-and-replaces** instead of silently over-counting. A flight whose aircraft can't be resolved now surfaces "counters NOT updated" instead of a silent success *(pending decision F4 — default: warn, don't block)*.

## 4. Maintenance lifecycle (cross-module spine) — Fleet → MRO
1. **Technical Data**: author the Maintenance Programme (Visits/Tasks + counter thresholds/intervals), approve it, assign it to aircraft.
2. **Fleet › Maintenance Planning**: fleet-wide due queue (Overdue / Due Soon / OK, with calendar next-due dates from the utilization forecast). Select items → **Build Work Package**.
3. **MRO › Work Package**: the builder creates the WP + tasks (emitting MRO status values directly — fork M1, so no first-save validation failure) → WP detail / execution.
4. **MRO**: create Work Orders under the WP; log Time Sheets against WOs.
5. **MRO › Record Completion**: writes per-item compliance with the live reading → the due engine rolls next-due forward → the item leaves the Overdue queue.
- **↑ change:** old hand-off broke because the builder wrote statuses outside the MRO enum (first save failed) and code-gen could 500 on a collision. v2: shared status vocabulary + collision-safe codes + a one-shot build token (no double-WP on double-click).

## 5. Airworthiness review — Fleet › Airworthiness
1. Pick aircraft (+ optional as-of date) → verdict: **AIRWORTHY / NOT AIRWORTHY / REVIEW INCOMPLETE**, with the five criteria (Work Packages, AMP, Tech Pubs, Defects, Config) each PASS / FAIL / NOT EVALUATED and the failing reasons listed.
2. Each criterion links to its source screen (e.g. FAIL on Defects → the open-defects list for that tail).
- **↑ change:** old verdict was read-only with no drill-down; v2 makes every criterion a link to the screen that can fix it. "Never fake-green" preserved (NOT EVALUATED stays honest).

## 6. Defects — MRO / Flight / Aircraft Card (shared)
1. List (by tail or fleet) → open a defect (read-only) → Edit.
2. Mark Deferred ⇒ MEL category + expiry required; mark Closed ⇒ closed date required (enforced).
3. Open/Deferred + MEL-expired feed §5 airworthiness.
- **↑ change:** the same defect surface embeds consistently in all three hosts (no divergent mini-forms). Counter snapshots are preserved on edit instead of being deleted-and-recreated.

## 7. Configuration control — Fleet › Configuration
1. Pick aircraft → installed-base vs applicable-config diff: **In Sync / Missing / Missing Qty** per part number.
2. Compare view drills into the L1→L2→L3 tree.

## 8. Reference data (master data) — Administration / Technical Data
Pencil-launched modal CRUD for reference tables (counter definitions, penalty rules, publication types, task types, locations, etc.): rows read-only until the pencil is clicked. *Consistent across every reference screen; one interaction model.*

## 9. Dashboard
Landing after login: fleet status snapshot + quick actions that deep-link into the registration-scoped screens above. *Read-only overview; every tile is a jump-off, not a dead end.*

---

## Inter-module map (who hands off to whom)
- Counter save → Inventory (remaining), MRO (task status), Flight (daily log), Compliance (evidence).
- Technical Data (AMP) → Fleet (due engine) → MRO (work package) → Fleet (compliance closes the loop).
- Everything → Airworthiness (read-only aggregate).

## Open flow questions (defer to their phase)
- F4 — unmatched-aircraft flight: warn vs block (default warn).
- M2/M3 — WO lifecycle + Time Sheet approval screens (columns exist; build when those decisions land).
- R5 — historical as-of for all criteria (today Defects-only).
