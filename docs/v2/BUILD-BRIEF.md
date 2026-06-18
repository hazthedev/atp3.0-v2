# ATP 3.0 v2 — Build Brief

## Role & Mindset
Act as a senior software engineer who keeps learning and improving the system as a
whole — not a transcriber. As you analyze the existing backend flow, if you can see
a better way (cleaner data model, simpler control flow, fewer moving parts, better
separation of concerns, a more robust concurrency approach), DO IT — but:
- Preserve the BUSINESS RULES exactly. Improve the implementation, never silently
  change what the system is supposed to do.
- When you make a structural improvement, log it in docs/v2/improvements.md: the old
  approach, what you changed, and why it's better. I will review this.
- When an improvement would change observable behavior or a business rule, STOP and
  ASK first. Improving HOW is your call; changing WHAT is mine.

Treat the existing ATP 3.0 codebase as READ-ONLY reference. Do not clone and evolve
in place. Do not copy old files wholesale. Port deliberately. Pause at each [REVIEW].

## Stack (verified current, June 2026)
- Laravel 13 (March 2026; requires PHP 8.3 min)
- PHP 8.3+
- Livewire 4 (current stable; PHP 8.3 min; deferred wire:model is now default — use
  .live explicitly for real-time fields)
- Tailwind CSS 4.3 (Oxide engine, CSS-first @theme config, OKLCH colors, Vite plugin)
- Alpine.js (latest, ships with Livewire 4)
- MySQL (use a Laravel 13-supported version — confirm at scaffold)

Stack notes:
- Use Laravel 13 native PHP attribute syntax (#[Table], #[Fillable], etc.).
- Define the design system via Tailwind 4 @theme with OKLCH tokens.
- Lean on Livewire 4 batched requests + WASM diffing for dense tables, not a JS SPA.
- Before pinning versions, run `composer show laravel/framework` and
  `npm view tailwindcss version`. Verify, don't assume.

## Phase 1 — Scaffold + stack setup
- FIRST confirm PHP 8.3+ is available locally. If not, stop and tell me.
- Scaffold a clean project on the confirmed stack. No legacy layout.
- Wire up Tailwind 4 (@theme/Vite), Livewire 4, Alpine, clean base layout.
- Set up MySQL; confirm migrations run against MySQL (NOT SQLite).
- [REVIEW] Show project structure and confirmed versions.

## Phase 2 — Domain extraction (logic, not code)
- Read existing ATP 3.0 and extract business rules into docs/v2/domain-spec.md:
  counter/penalty engine (incl. cascade logic), flight/daily-update pipeline,
  upgrade-readiness auditing, core MRO workflows and state transitions.
- Plain-language description + precise logic statement. SPEC only, no v2 code.
- As you map each flow, note where the old design is awkward/fragile/overcomplicated
  and propose a better approach. Keep the rule; improve the mechanism.
- CRITICAL: old concurrency guards were silent no-ops on SQLite and the MySQL proof
  test was skipped in CI. Flag every place needing real concurrency protection
  (tested against MySQL). Do not re-inherit this bug.
- [REVIEW] Approve domain-spec.md + proposed improvements before any porting.

## Phase 3 — Fresh schema + seeders
- Design schema fresh from the approved domain model. No old migrations, no prod
  data. Fix data-model problems flagged in Phase 2.
- Clear naming, sensible normalization, explicit FKs, proper indexes, intended model
  over legacy compromises.
- Seeders with realistic sample data for dev/testing only.
- [REVIEW] Show a Mermaid ERD + migration list.

## Phase 4 — User flow mapping
- Map end-to-end journeys per module; obvious steps, logical inter-module nav.
  Document in docs/v2/user-flows.md.
- Where old flow was confusing/dead-ended/too much work, design a clearer one and
  note what changed and why.
- [REVIEW] Approve flows before building screens.

## Phase 5 — Port domain logic onto new schema
- Implement approved rules as clean Laravel 13 code on the fresh schema, applying
  the Phase 2 improvements. Not the old structure/patterns.
- Real concurrency protection per Phase 2 flags. Tests that PROVE guards work
  against MySQL (not SQLite).
- [REVIEW] Walk me through the counter/penalty engine implementation.

## Phase 6 — Design system + UI rebuild
DESIGN DIRECTION: Linear-like — minimal, sharp, modern, high-craft.
- Use the frontend-design skill for tokens and component foundations.
- Establish FIRST, before screens: tight spacing scale, clear type hierarchy, muted
  neutral OKLCH palette with ONE disciplined accent, crisp borders over heavy
  shadows, fast/quiet interactions. Define via Tailwind 4 @theme.
- Dense where data demands it (enterprise MRO) but calm. Dense ≠ cluttered.
- AVOID the "AI-generated" look: no gradient overload, no emoji headers, no
  purple-everything, no card soup, no decorative noise. Every element earns its place.
- Build tokens + core components first, [REVIEW], THEN rebuild UI module by module.

## Ground Rules
- All planning docs in docs/v2/ (domain-spec.md, improvements.md, user-flows.md).
- One phase at a time. Stop at every [REVIEW] and wait for approval.
- Improve freely at implementation level; ask before changing any business rule or
  observable behavior.
- When unsure about a domain rule, ASK rather than guess.
- Commit at end of each phase with a clear message; ask before committing.
