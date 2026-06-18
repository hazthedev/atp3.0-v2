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

<!-- Further entries appended as improvements are made during Phases 3–6. -->
