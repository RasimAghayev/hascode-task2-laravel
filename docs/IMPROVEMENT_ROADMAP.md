# Improvement Roadmap — Phase 4-11 plan for `hascode-task2-laravel`

> This is the per-repo execution plan referenced by **Gate 10**. Nothing here is executed by Phase 3 — it is the plan to be approved before Phase 4 starts. Phase numbers and scopes follow `PROMPT.md`/`CLAUDE.md`. Standing decisions referenced throughout (1, 2, 5, 12, 17) are the ones most load-bearing for this specific repo.

## Critical path (must happen in this order)

1. **Phase 4** — stand up the 3-branch model (`preview`/`test` don't exist yet); triage all 12 open bot PRs, starting with `#76` (the mergeable, CVE-clearing `laravel/framework` bump — see below); `master`→`main` rename is **Gate 4**.
2. **Phase 6, step 0** — resolve the composer manifest/lockfile mismatch (`docs/TECHNICAL_DEBT.md` P0-1). Expect to need `laravel/framework` landed first/alone before `laravel/passport`/`laravel/tinker` resolve — do not assume a single unscoped `composer update <6 packages>` succeeds in one shot.
3. **Phase 6, step 1** — `laravel/framework` to `>=12.68.0` or `>=13.27.0` (latest stable at run time), clearing `CVE-2026-114509` for real. PR `#76` already proposes a version inside the clearance floor; evaluate reusing/superseding it rather than starting fresh.
4. **Phase 5** — build the Docker baseline from scratch (no Dockerfile/compose/Makefile exists today) — needed before any phase can honestly claim "verified in Docker."
5. **Phase 8/9** — decide and implement the ownership/authorization model for `hashes` (P1-1) and fix the `store()`/`update()` logic defects (P1-2/P1-3) — the repo's two biggest concrete correctness/security gaps.

Everything else (dead-code removal, docs, CI, community standards) can proceed in parallel with or after the above.

## Phase 4 — Git/GitHub hygiene

- Full-history `gitleaks` audit (this Phase 3 pass only covered the working tree, per the orchestrator's directive — expected clean given the working-tree result, must still be run properly).
- Triage all 12 open bot PRs. Already individually diffed this phase (do not re-diff from scratch):
  - `#76` (`renovate/packagist-laravel-framework-vulnerability`) — mergeable, resolves to `v12.71.0`, clears `CVE-2026-114509`. Strong candidate to merge directly or let Phase 6 supersede with an equivalent/better result.
  - `#86` (`renovate/laravel-passport-10.x`), `#89` (`renovate/laravel-tinker-2.x`) — both propose downgrading the manifest to match the stale lock; **do not merge as-is** — they would lock in the mismatch rather than fix it. Close as superseded once Phase 6 resolves the real upgrade path.
  - `#71` (sanctum→v4), `#82` (phpunit security), `#88` (axios lockfile), `#87` (postcss lockfile), `#90` (collision), `#91` (ignition), `#1`/`#74`/`#72` (Dependabot: guzzle, express, follow-redirects) — not yet individually diffed; Phase 4 diffs each against the post-Phase-6 state and merges/closes per the standard process.
- 0 open issues — nothing to triage there.
- Configure `.github/dependabot.yml` (standing decision 14: weekly Monday 06:00 UTC, Composer + npm + Docker + GitHub Actions, target branch `preview`, limits 10/10/10/5/5, commit prefixes `build(deps)`/`build(deps-dev)`/`ci(deps)`). This repo already runs Renovate live — bring it to the same policy rather than adding Dependabot redundantly, per standing decision 14's own instruction.
- Stand up `preview`/`test` (don't exist yet); keep `main` pending the Gate 4 rename.
- `master`→`main` rename — **Gate 4**, bundle with this Gate 10 presentation.
- Discussions/Wiki — already enabled, no action needed (re-confirmed this phase).
- Acceptance: gitleaks full-history exit 0 or findings triaged; bot PRs resolved or explicitly deferred to Phase 6 with a documented reason; 3-branch model in place.

## Phase 5 — Container baseline

- Author a genuine multi-stage Dockerfile: `base` (pinned PHP image + digest) → `deps` (`composer install`) → `build` → `test` (runs `phpunit`/`php artisan test`) → `runtime` (non-root, `HEALTHCHECK`, exec-form entrypoint).
- `docker-compose.yml`: app service + PostgreSQL (matching `.env.example`'s `DB_CONNECTION=pgsql`), healthchecks, `depends_on: condition: service_healthy`.
- `Makefile`: `build`/`test`/`verify`/`up`/`down`/`shell`/`migrate` targets per the Docker standard.
- `docs/BASELINE.md`: record the *actual* result of running the existing test suite in the new `test` stage — including whether `HashTest::test_find_hash()`'s broken helper chain (P2-7) causes a failure, as the honest starting baseline, not a guessed one.
- `.env.example` already exists — verify it's complete enough for the new compose stack; add anything missing (e.g. if Redis is kept, document it; if removed per P3-3, drop those vars too).
- Acceptance: image builds standalone; `make verify` green or the red state is documented as the pre-existing baseline (per CLAUDE.md's "don't hide, don't blame the phase" rule) — this repo has never been run in Docker before, so a red baseline here is plausible and acceptable, not a Phase 5 failure.

## Phase 6 — Upgrade to latest (standing decision 1 — read live, never from memory)

| Step | Target | Note |
|---|---|---|
| 0 | Resolve composer manifest/lockfile mismatch | Prerequisite for everything else in this table — see Critical path above and `docs/TECHNICAL_DEBT.md` P0-1. |
| 1 | `laravel/framework` → true latest stable (read live at run time; was `v13.44.1` as of this Phase 2/3 pass, re-check at Phase 6 time) | Clears `CVE-2026-114509`. PR `#76`'s `v12.71.0` target also clears it but is not the true latest major — Phase 6 should go the rest of the way to latest per standing decision 1, not stop at "CVE cleared." |
| 2 | `laravel/passport` → true latest stable | Manifest already says `^13.0`; confirm the true latest satisfies it or needs a further bump, re-queried live. |
| 3 | `laravel/tinker`, `nunomaduro/collision`, `spatie/laravel-ignition` → true latest stable | Dev-tooling tier, lower risk, same lockfile-regen step as #0 should mostly resolve these together. |
| 4 | `laravel/sanctum` | Confirmed unused (P2-3) — bump if kept pending the Phase 7 removal decision, or skip the bump and remove it outright here if Phase 7 ordering allows pulling that decision forward. Either way, document which was chosen. |
| 5 | `guzzlehttp/guzzle` | Framework-bundled; bump follows naturally from step 1-3's framework/dev-tool updates — re-verify the 11 advisories clear. |
| 6 | PHP runtime floor | `composer.json` currently floors at `^8.0.2` — re-check PHP's live EOL schedule at Phase 6 time (PHP 8.2 reaches EOL 2026-12-31, close to this pass's date) and raise the floor to whatever the then-current supported minimum is; no Dockerfile exists yet to pin a base image version until Phase 5 lands (sequence Phase 5 before or alongside this step). |
| 7 | `laravel-mix`/webpack asset pipeline | Not a simple version bump — see `docs/TECHNICAL_DEBT.md` P0-3. Evaluate a Vite migration (standing decision 3 decision entry in `docs/DEPENDENCY_DECISIONS.md`: what it replaces, maintenance status, license, size) vs. documenting a standing-decision-1 blocker if migration is deferred. |
| 8 | Direct npm deps (`axios`, `bootstrap`, `popper.js`, `sass`) | Already clean per `npm audit`'s direct-dependency breakdown; bump to latest regardless, re-queried live. |
| 9 | CI action SHA-pinning | N/A yet — no CI exists (Phase 10 creates it; pin by SHA from day one rather than bumping later). |

- **Standing decision 17 check, per step**: before closing each step, skim that release's own changelog/migration notes for anything that supersedes code already in this repo. Concretely to watch for here: Laravel 11+'s streamlined `bootstrap/app.php`/middleware registration (this repo still has the full Laravel 10-era `app/Http/Kernel.php`/`app/Exceptions/Handler.php` split) is a strong candidate for adoption once the framework bump lands — hand off to Phase 7 if not done inline, per decision 17's "name it as a follow-up" allowance, not left silently on the table.
- Each step: bump → `make verify` in Docker → commit (`build(deps):`/`chore(deps):`) → next. Breaking changes migrated and logged in `CHANGELOG.md`.
- Merge/close the 12 open bot PRs per Phase 4's triage, superseding with this phase's own result where they overlap.
- Configure `.github/dependabot.yml` if Phase 4 hasn't already (standing decision 14).
- Acceptance: every dependency/runtime at latest stable or a documented, re-attempted blocker; `composer audit --locked`/`npm audit` counts collapse as expected or each remaining finding is documented; `make verify` green.

## Phase 7 — Dead code & restructure

- Remove (separate commits, after Phase 6 lands, tooling+coverage evidence, never on a guess):
  - `public/js/app.js` (642 KB committed build artifact, P2-2) and add the proper `.gitignore` rule.
  - `laravel/sanctum` (P2-3), once re-confirmed still unused post-upgrade.
  - Commented-out dead `if ($validator->fails())` blocks in `store()`/`update()` (P2-5).
  - `AuthController.php`'s unused `use Hash;` import (P2-6).
  - Redis config block in `.env.example`/`config/` if the Phase 7/8 judgment call (P3-3) is to drop it rather than wire it in.
- Re-shape to the upgraded Laravel version's conventions (standing decision 17's `bootstrap/app.php` modernization, if not already done in Phase 6).
- Fix `HashController::store()`'s dedup-logic defect (P1-2) and `update()`'s undefined-key read (P1-3) — these are bugs, not dead code, but sit in the same methods Phase 7's tooling pass will already be touching; coordinate with whichever phase (7 or 9) actually lands the fix so it isn't done twice.
- Acceptance: no known dead code left; structure matches the upgraded stack; `make verify` green.

## Phase 8 — Architecture

| Principle | Likely verdict for this repo | Why |
|---|---|---|
| DRY | Probably not applicable | Single controller, single resource, no repeated logic found across ≥2 call sites in this audit. |
| KISS/YAGNI | Applies by default | Keep the fix for P1-2/P1-3/P1-4 minimal — don't introduce a repository/service layer for a one-model CRUD API that doesn't need one. |
| SOLID | Probably not applicable | `HashController` has one reason to change; no concrete coupling blocking tests was found. |
| Clean/Hexagonal | Not applicable today | One external dependency (PostgreSQL via Eloquent), no second implementation to support, no present requirement for one. |
| DDD | Not applicable | Flat `hashes` table, no relations, no real domain invariants beyond field-level validation. |
| EDA | Not applicable | No async reactions or cross-service integration exists or is planned. |
| Modular monolith | Not applicable | One module (`hashes`), no competing internal boundaries to formalize. |
| GoF | Only if P1-1 (ownership) surfaces a real need | A policy-based authorization pattern (Laravel's own `Policy` class) would be the natural fix for P1-1, not a GoF pattern per se. |

The one real architectural decision this repo needs an ADR for: **the ownership/authorization model for `hashes` (P1-1)** — add a `user_id` column + policy, or document a deliberate single-tenant-by-design decision. This is the item most likely to actually move the needle; everything else in the table above is a documented "doesn't apply" per KISS/YAGNI.

## Phase 8B — Feature enhancement (Gate 12, never pre-approved)

Not pre-judged here — Phase 8B's own agent proposes 2-3 candidates once Phase 8 stabilizes. Plausible, non-binding candidate directions given this repo's actual scope (a hash-dedup/lookup CRUD API): a `GET /api/hash/search?data=` lookup-by-content endpoint (reusing `showData()`'s already-half-built logic, once fixed), or a bulk-create endpoint. Final candidates are Phase 8B's call, with business value/acceptance criteria/test plan, same rigor as every other repo.

## Phase 9 — Hardening

- **Security**: implement the ownership/authorization decision from Phase 8 (P1-1); fix `login()`'s `$request->all()` pattern (P2-8); author `config/cors.php` deliberately once a real consumer is confirmed (P2-9); OWASP Top 10 floor applies even to this small a surface.
- **Performance**: `Hash::all()` pagination (P2-12) only if row-count evidence justifies it — not a default action.
- **Dual-mode**: add `/health/live`+`/health/ready`, `openapi.yaml` describing the (by-then-fixed) `hash`/`hash0` contract.
- **Observability**: structured logging — currently none beyond Laravel's defaults.

## Phase 10 — CI/CD

- New `.github/workflows/ci.yml` from zero: cheap checks (lint/format) → test stage (Docker `test` target) → `composer audit`/`npm audit` → image build validation. Target ≤10 min wall-clock.
- SHA-pin every action from the start (no legacy floating-tag debt to migrate, since there's no existing CI to inherit from).
- GHCR publish-on-tag: evaluate per the CI/CD standard's default expectation vs. this repo's actual scope (a small interview-style task repo per its own README framing) — Phase 10's judgment call, document either way (an ADR like `taskStarEx`'s precedent if skipped).

## Phase 11 — Tests

- Fix `HashTest::test_find_hash()`'s broken helper chain (P2-7) as real test-writing work, not a Phase 7 "dead code" deletion.
- Add coverage for the P1-2/P1-3/P1-4 fixes once they land (regression tests proving the dedup logic, the validated-key fix, and the route-parameter semantics actually work as intended).
- Add authorization tests once P1-1 lands (authenticated-but-not-owner gets 403/404, per whatever Phase 8 decides).
- Regression vs. `docs/BASELINE.md`; honest coverage reporting, never report an unexecuted test as passing.

## Distributed / multi-server deployment feasibility (roadmap item only — not implemented, not scheduled into a phase above)

This app is a strong candidate for a straightforward stateless-replica deployment, **if and when that's ever wanted** — this is a feasibility note for later consideration, not a commitment to build it:

- **State**: all persistent state lives in PostgreSQL; Passport tokens are stored in the `personal_access_tokens` table (DB-backed, not server-local sessions/files), so any number of app instances can validate the same token — no sticky-session requirement.
- **Caching/queues**: `CACHE_DRIVER=file`/`SESSION_DRIVER=file`/`QUEUE_CONNECTION=sync` are all **local-filesystem or synchronous today** — these would need to move to Redis/database-backed drivers (Redis is already configured in `.env.example`, just unused — see P3-3) before running more than one instance, since file-based cache/session wouldn't be shared across replicas.
- **No in-process background work**: `QUEUE_CONNECTION=sync` means nothing currently relies on a queue worker's local state, which is actually a point in favor of easy horizontal scaling (nothing to coordinate) — though it also means there's no async work happening at all today (acceptable for this app's current size).
- **Database**: a single PostgreSQL instance is the one true shared dependency; horizontal app scaling would need the DB to handle the combined connection/query load, which the current schema (one small table, no heavy queries, no N+1) would not stress at any plausible scale for this app's apparent purpose.
- **Verdict**: technically straightforward (stateless app tier, DB-backed auth, no sticky sessions) **once** the cache/session drivers move off the local filesystem. Not recommended to actually build without a concrete reason — per KISS/YAGNI, this app's current scope (a small CRUD/demo API) doesn't present a present need for multiple server instances. Recorded here per the task's own instruction to cover this as a roadmap item, not as a Phase 5/16 commitment.

## Where each standing decision lands on this repo

- **Decision 1 (latest, always)**: every dependency table above is driven by live registry reads at Phase 6 time, not this phase's snapshot — re-query, don't reuse Phase 2/3's numbers as-is once Phase 6 actually runs.
- **Decision 2 (Docker-first, container-only toolchain)**: this phase ran zero host invocations of PHP/Composer/Node/npm — the one Phase 2 containerized run (`composer validate`/`audit`, `npm audit`) is cited, not repeated. Phase 5 onward must build a real Docker baseline before any `make verify` claim is valid.
- **Decision 5 (3-branch model)**: this repo is a from-zero case — Phase 4 creates `preview`/`test` fresh, no prior 2-branch history to reconcile.
- **Decision 12 (no AI attribution)**: the Phase 3 commit on `phase-3/audit-plan` was authored and checked for any auto-appended trailer before push — see the Phase 3 handoff for the explicit verification.
- **Decision 17 (adopt what the version brings)**: flagged above in Phase 6's table (Laravel's streamlined bootstrap/middleware registration) as the one concretely-identifiable candidate for this repo; re-check the actual release notes at Phase 6 time for anything else.
