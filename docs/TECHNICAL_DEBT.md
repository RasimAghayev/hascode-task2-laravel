# Technical Debt

> Classified P0-P3. Cross-referenced to `projects/info/analysis/hascode-task2-laravel.md` and `docs/REPOSITORY_AUDIT.md`. Remediation owner = the phase that will act on it. **This phase (3) documents only — nothing below has been executed.** No item is marked "Resolved"; that would misrepresent work not yet done.

## P0 — Critical

| ID | Item | Owner phase | Planned remediation |
|---|---|---|---|
| P0-1 | `composer.lock` fails `composer validate` against `composer.json` on `master` itself (6 packages: `laravel/framework`, `laravel/passport`, `laravel/sanctum`, `laravel/tinker`, `nunomaduro/collision`, `spatie/laravel-ignition`) | Phase 6 (step 0, prerequisite) | Run `composer update` for the 6 packages inside a disposable container. **Expect resolution friction, not a clean one-shot**: 2 of the 12 open Renovate PRs (`#86` passport, `#89` tinker) propose *downgrading* the manifest to match the stale lock rather than upgrading the lock — evidence the bot's own resolver hit a conflict, most likely needing `laravel/framework` bumped first/alone. See `docs/REPOSITORY_AUDIT.md` Dependency analysis. |
| P0-2 | `CVE-2026-114509` — Laravel TokenGuard auth bypass, in range at the locked `v9.17.0` | Phase 6 (step 1) | Bump to `>=12.68.0` or `>=13.27.0` (latest stable at run time, standing decision 1). **A ready-to-merge fix already exists**: open PR `#76` (`renovate/packagist-laravel-framework-vulnerability`) resolves to `v12.71.0`, inside the clearance floor — Phase 6 should evaluate merging/superseding this PR directly rather than starting from scratch, since it is mergeable today. |
| P0-3 | 47 vulnerable npm packages (6 critical) on the `laravel-mix` asset pipeline, all transitive | Phase 6 | `laravel-mix` is abandoned (no release since 2022); bumping its own version will not clear these. A migration-to-Vite decision (standing decision 3 entry in `docs/DEPENDENCY_DECISIONS.md`) is the real fix, not a version bump — document as a standing-decision-1 blocker if migration isn't completed in the same pass, with the 4 direct npm deps (`axios`, `bootstrap`, `popper.js`, `sass`, all clean per `npm audit`'s direct breakdown) carried forward regardless of which build tool hosts them. |

## P1 — High

| ID | Item | Owner phase | Planned remediation |
|---|---|---|---|
| P1-1 | Zero authorization on `hashes` (any authenticated user can read/update/delete any other user's rows); no `user_id` column, no policy/gate | Phase 8 (ADR: add ownership or accept single-tenant-by-design) → Phase 9 (implementation) | If ownership is the right model: add a `user_id` foreign key + migration, scope every `HashController` query to `auth()->id()`, add a `HashPolicy`. If this app is deliberately single-tenant/demo-scope (plausible given its size), document that as the explicit reason in an ADR instead of adding unneeded scaffolding — Phase 8's judgment call, not pre-decided here. |
| P1-2 | `HashController::store()`'s dedup logic is broken: `showData()` returns an `int` or a `Response` object; `store()` array-indexes the result as if it were an array | Phase 7 (bugfix, inseparable from the dead-code pass touching this method) or Phase 9 | Rewrite `showData()` to return a consistent type (e.g. `?Hash` or `?array`, never a `Response`); fix `store()`'s consumption of it accordingly. Needs a real regression test first (Phase 11 territory, but a minimal characterization test per Phase 5's baseline rule should exist before this is touched). |
| P1-3 | `HashController::update()` reads `$validator['hash0']`, a key never present in the validated array (only `data` is validated) | Same owner/phase as P1-2 — same method family, same fix pass | Validate `hash0` explicitly if it's meant to be client-supplied, or stop reading it and recompute server-side consistent with `store()`'s own logic once P1-2 is fixed. |
| P1-4 | `{hash}` route parameter means `hash0` for `GET`/`DELETE`+`PUT` mean `id` — same URL shape, two different lookup semantics; `hash0` is never returned to the client by `HashResource` | Phase 7/8 (API contract fix, likely needs an ADR since it changes response shape) | Either expose `hash0` in `HashResource` and make all four verbs resolve by it consistently (with `getRouteKeyName()` on the `Hash` model), or standardize on `id` everywhere and drop `hash0`-based lookup from `show()`. This changes the repo's own public API contract — flag for **Gate 5** if any external consumer is known to depend on the current shape (none confirmed in this audit; Phase 4's discussion/issue triage should double-check before Phase 8 commits to a direction). |
| P1-5 | Docker-first standing decision not achievable today: no Dockerfile/compose/Makefile exists at all | Phase 5 | Build the full container baseline from scratch (multi-stage Dockerfile with a real `test` target, `docker-compose.yml` for app+Postgres, `Makefile` targets) — this repo is a "build from zero" case like `be-invoice`, not an "improve existing" case. |
| P1-6 | No CI pipeline, no Dependabot config, no community-standard files — `.github/` doesn't exist | Phase 4 (Dependabot) / Phase 10 (CI) / Phase 15 (community files) | Standard creation pass, same as every repo starting from zero. |

## P2 — Medium

| ID | Item | Owner phase | Planned remediation |
|---|---|---|---|
| P2-1 | `.idea/` (11 files) was tracked in git, `.gitignore` didn't cover it | Phase 3 (this phase) | **Done this phase.** `git rm --cached .idea -r`; `.idea/`, `.vscode/`, `*.swp`/`*.swo` added to `.gitignore`. |
| P2-2 | `public/js/app.js` (642 KB `laravel-mix` build output) committed to git; `.gitignore` never got `public/js`/`public/css` exclusions | Phase 7 | `git rm --cached`, add `public/js`/`public/css` (or the specific compiled filenames) to `.gitignore`, rebuild via the real `npm run production` step once a container exists to run it in (Phase 5). |
| P2-3 | `laravel/sanctum` declared, confirmed unused by grep (Passport is the real guard) | Phase 7 | Remove from `composer.json`/`composer.lock` after re-confirming zero usage survived the Phase 6 upgrade. |
| P2-4 | README stale in 2 of its 4 "Notes" bullets (claims an `api/` subdirectory that doesn't exist; claims `print_r`/`die()` debug code removed in commit `b63b207`) | Phase 15 (or incidental whenever README is next touched) | Rewrite the "Notes" section against the actual current code; drop the `api/`-path architecture diagram claim. |
| P2-5 | Commented-out dead `if ($validator->fails())` blocks in `store()`/`update()` (unreachable — `$request->validate()` already throws) | Phase 7 | Delete, same pass as the P1-2/P1-3 bugfixes since they touch the same methods. |
| P2-6 | `AuthController.php`'s `use Hash;` — unused import, name-collides with `App\Models\Hash` | Phase 7 (dead-import removal via static tooling) | Remove the unused `use` statement. |
| P2-7 | Broken test helper chain: `HashTest::test_find_hash()` calls `$this->test_create_hash()->hash` on a method with no return statement | Phase 11 | Fix the test (give `test_create_hash()` a real return value, or restructure as a proper helper not itself a test method) as part of the real test-writing pass; verify in Docker, don't just assume it was passing. |
| P2-8 | `login()` passes `$request->all()` to `auth()->attempt()` instead of just the validated `email`/`password` | Phase 9 | Tighten to pass only the validated credential array. |
| P2-9 | No `config/cors.php` published | Phase 5 (verify behavior once a container exists) → Phase 9 (author deliberately if a cross-origin client is ever confirmed) | Author explicitly once a real client/CORS requirement is confirmed; don't guess at an origin list with no consumer to validate against. |
| P2-10 | `.env.example`'s `APP_KEY` is a real-shaped generated value, not an obvious placeholder | Phase 15 (community-standards pass) | Replace with an empty or obviously-placeholder `APP_KEY=` entry. |
| P2-11 | 12 open bot PRs, 9 of them not yet individually triaged by this audit (only `#76`/`#86`/`#89` were diffed) | Phase 4 | Full triage: diff each against current `master`, merge/supersede/close per Phase 4's standard process; Phase 6 owns the ones that are genuine version bumps once the composer-resolution order (P0-1) is sorted out. |
| P2-12 | `Hash::all()` has no pagination | Phase 7/8 (judgment) | Add pagination only if row counts are ever shown to be large enough to matter — not a default "fix," per KISS/YAGNI; note and re-check at Phase 9's performance pass. |

## P3 — Low

| ID | Item | Owner phase | Planned remediation |
|---|---|---|---|
| P3-1 | `composer.json`'s `"name"`/`"description"` still the generic `laravel/laravel` skeleton | Phase 15 | Customize to reflect the actual project. |
| P3-2 | Default branch still `master`, no `preview`/`test` branches | Phase 4 | **Gate 4** applies for the `master`→`main` rename; `preview`/`test` creation is not gated, standard Phase 4 work. |
| P3-3 | Redis configured in `.env.example` but never actually used anywhere in `app/` (cache/session/queue drivers are all `file`/`sync`) | Phase 7 (judgment) | Either wire it in for a real purpose or remove the unused config block — Phase 7/8 judgment call, not a defect requiring urgent action. |
