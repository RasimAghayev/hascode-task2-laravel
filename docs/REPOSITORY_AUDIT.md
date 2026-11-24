# Repository Audit

> Phase 3 full-discovery audit. All findings are derived from real code/config in this repo at `master` (`8148b14`), Phase 2's dependency data (`projects/info/deps/hascode-task2-laravel.md`), and live checks (`gh`, `gitleaks`) run during this phase. Nothing below is inferred from memory of similar projects. Per the mid-task tightening of standing decision 2, no host invocation of this repo's own toolchain (`php`/`composer`/`node`/`npm`) occurred in this phase — the one Phase 2 `composer validate`/`composer audit --locked`/`npm audit` run cited below already ran inside disposable containers; this phase added no new toolchain runs, only `git`/`gh`/`gitleaks` (infrastructure, not this repo's own toolchain).

## Scope and method

- Full read of the app (routes, controllers, models, resources, middleware, migrations, config, tests, `.env.example`, `.gitignore`, `composer.json`/`.lock`, `package.json`/`.lock`, `webpack.mix.js`, `README.md`).
- Dependency currency/vulnerability data is inherited from Phase 2 (`projects/info/deps/hascode-task2-laravel.md`) and cited, not re-run — except one piece of **new evidence this phase surfaced**: diffing the 10 open Renovate PR branches against `master` (see Dependency analysis below), which Phase 2's remit didn't include.
- This phase made exactly one working-tree change: untracking `.idea/` (11 files) and adding `.idea/`/`.vscode/`/swap-file patterns to `.gitignore`, per `CLAUDE.md`'s "first action on any repo" IDE-artifacts rule. No application code was touched.
- Live, read-only checks run this phase: `gh repo view` (existence/visibility/default-branch/Discussions/Wiki re-confirmation), `gh pr list`/`gh issue list` (bot-PR/issue inventory), `git log`/`git diff`/`git merge-base` against `origin/master` and each open Renovate branch (manifest-drift root-cause tracing), `gitleaks dir . --redact --no-banner` (working-tree secret scan).

## Dependency analysis (summary — full detail in `projects/info/deps/hascode-task2-laravel.md`)

- **`composer.lock` fails `composer validate` against `composer.json` on `master` itself** — 6 packages (`laravel/framework`, `laravel/passport`, `laravel/sanctum`, `laravel/tinker`, `nunomaduro/collision`, `spatie/laravel-ignition`) where the locked version doesn't satisfy the manifest's own declared range. Confirmed via `composer validate --no-check-all` inside a disposable `composer:2` container (Phase 2), exit 2.
- **1 critical CVE already in range at the locked version**: `CVE-2026-114509` (Laravel TokenGuard auth bypass), present at the locked `laravel/framework v9.17.0` — a CVE already tracked against 2 other repos in this program (`AC-Task`, `taskStarEx`).
- **New this phase — the 12 open bot PRs are not a uniform "lock never regenerated" pattern.** Diffing each Renovate branch against current `master`:
  - `renovate/packagist-laravel-framework-vulnerability` (**PR #76**, mergeable, opened 2026-11-10) proposes `composer.json` `laravel/framework ^10.0 → ^12.0`, resolving to `v12.71.0` — **inside** `CVE-2026-114509`'s `>=12.68.0` clearance floor. This is a genuine, ready-to-merge fix for the program's critical CVE on this repo, sitting unmerged. Its own badge metadata (compatibility check `9.17.0 → 12.71.0`) independently reconfirms the currently-locked version.
  - `renovate/laravel-passport-10.x` (**PR #86**, mergeable) and `renovate/laravel-tinker-2.x` (**PR #89**) instead propose **downgrading** the manifest (`^13.0 → ^10.4`, `^3.0 → ^2.11`) to match what's already (stale-)locked, rather than upgrading the lock to match the manifest. Both branches were created *after* the manifest's current ranges were already merged (confirmed via `git merge-base --is-ancestor`), so this isn't branch staleness — it is Renovate's own dependency resolver signaling it could not produce a clean upgrade path for `laravel/passport ^13.0`/`laravel/tinker ^3.0` against the rest of the currently-locked graph (most plausibly because `laravel/framework` is still locked at `v9.17.0`, and Passport 13 needs a materially newer framework). **This means Phase 6 should expect the composer graph to need the framework bump landed first, possibly alone, before passport/tinker/sanctum/collision/ignition resolve cleanly** — not a one-shot `composer update <6 packages>` the way `taskStarEx`'s regeneration was.
  - The other 7 open PRs (`renovate/laravel-sanctum-4.x` #71, `renovate/axios-1.x-lockfile` #88, `renovate/postcss-8.x-lockfile` #87, `renovate/packagist-phpunit-phpunit-vulnerability` #82, `renovate/nunomaduro-collision-6.x` #90, `renovate/spatie-laravel-ignition-1.x` #91, plus Dependabot's `guzzlehttp/guzzle-7.4.5` #1, `npm_and_yarn/express-4.19.2` #74, `npm_and_yarn/follow-redirects-1.15.6` #72) were not individually diffed this phase — full triage is Phase 4/6's job, flagged here only as "12 total, not yet acted on."
- **`laravel-mix` is abandoned** (no release since 2022); its transitive webpack toolchain carries 47 known npm vulnerabilities (6 critical), none in the 4 direct npm deps (`axios`, `bootstrap`, `popper.js`, `sass`) themselves.
- No registry tokens, no `.npmrc`/`auth.json` secrets anywhere in the repo (Phase 2, re-confirmed by this phase's working-tree `gitleaks` pass finding nothing).

## Config review

| File | Finding |
|---|---|
| `.env.example` | Every credential field is placeholder-style **except `APP_KEY`**, which is a real base64-encoded 32-byte value rather than an obvious placeholder. Community-standards gap (not escalated as a live-secret Gate 1 finding — no evidence of production use, `gitleaks` found nothing). |
| `.env.example` | `DB_CONNECTION=pgsql` matches `README.md`'s own stated stack — no documentation/example mismatch here, unlike some sibling repos in this program. |
| `config/` | No `cors.php` published — CORS behavior relies on undocumented framework defaults; not verified at runtime (no container exists yet). |
| `.gitignore` (pre-Phase-3) | Covered `vendor/`, `node_modules/`, Laravel's storage/compiled paths, `.env`, `Homestead.*` — but **not** `.idea/`/`.vscode/` (11 `.idea/*` files were tracked) nor `public/js`/`public/css` (the compiled build artifact, see below). The `.idea/` gap is fixed this phase; the `public/js`/`css` gap is left for Phase 7 (dead-weight/build-artifact removal is that phase's remit, not a Phase 3 code change). |
| `public/js/app.js` | A 642 KB `laravel-mix` build output, committed to git. The repo has a working `npm run production` build step — this file should never have been tracked. |
| `README.md` | **Stale in two places, confirmed by reading the actual code**: claims the app "lives under `api/`" (there is no `api/` directory — the app is at repo root) and claims `HashController::store()` contains `print_r`/`die()` debug code (the current `store()` has neither — removed in commit `b63b207`, which the README's own "Notes" section was apparently never updated after). Two of the README's four "Notes" bullets (unused `laravel/sanctum`, hardcoded `.env.example` `APP_KEY`) remain accurate; the other two (stale `HashResource`/debug-code claims) do not — `HashResource` already uses an explicit field list, also per `b63b207`. |

## Code quality findings

- **`HashController::store()`'s dedup logic has a live defect**: `showData()` returns either the int `0` or a `Response` object (via `response([$hash])`); `store()` then array-indexes that return value (`$oldHash['hash0']`) as if it were an array. See `docs/ARCHITECTURE.md` Risk #1 for the full trace.
- **`HashController::update()` references an undefined validated key**: only `data` is validated, but the method reads `$validator['hash0']` — never populated. See `docs/ARCHITECTURE.md` Risk #2.
- **Inconsistent route-parameter semantics**: `GET /api/hash/{hash}` resolves by the `hash0` column; `PUT`/`PATCH`/`DELETE /api/hash/{hash}` resolve by `id` via Eloquent's default implicit binding (no `getRouteKeyName()` override on the `Hash` model). Same URL shape, two different meanings depending on verb. See `docs/ARCHITECTURE.md` Risks #3/#4.
- **`hash0` is never returned to the API client** (`HashResource`'s explicit field list omits it) despite being the only value `show()` accepts — compounds the above into a practical dead-end for a client trying to follow the documented contract.
- **No per-user ownership/authorization on `hashes`** — any authenticated user can read/update/delete any other user's rows; no `user_id` column, no policy/gate referencing `Hash` anywhere in the codebase.
- **`login()` passes the entire request body to `auth()->attempt()`**, not just the validated `email`/`password` pair — a looser pattern than passing exactly the validated fields.
- **Dead/confusing import**: `AuthController.php`'s `use Hash;` (root-namespace, unused) collides in name with this app's own `App\Models\Hash` business entity — confusing, not presently a functional bug since the import is never referenced.
- **Broken test helper chain**: `HashTest::test_find_hash()` calls `$this->test_create_hash()->hash`, but `test_create_hash()` has no `return` statement — this would be a hard null-method-call error if the suite were actually run. Not executed in this phase to confirm the failure mode empirically (Phase 3 is read-only); flagged for Phase 11 to verify and fix.
- **Commented-out dead code**: both `store()` and `update()` carry a commented-out `if ($validator->fails())` block — `$request->validate()` already throws on failure, so the block was always unreachable, but it was never deleted.
- No SQL-injection risk identified: the one raw-query use (`DB::table('hashes')->where(...)`) goes through Eloquent's parameterized query builder throughout.
- No N+1 risk identified — no relations are loaded anywhere in the controllers.
- `Hash::all()` in `index()` has no pagination — immaterial at today's likely row count, a real cost only at scale (see `docs/IMPROVEMENT_ROADMAP.md`).

## Security scan

- **Redacted secret scan of the working tree** (`gitleaks dir . --redact --no-banner`, this phase): exit 0, "no leaks found."
- Phase 2's prior checks (cited, not re-run): no registry tokens anywhere in the repo; no `.npmrc`/`auth.json`/`.yarnrc*` present.
- **Authorization**: none on `hashes` beyond "any authenticated user" — see Code quality findings above. This is the repo's primary security finding, same shape as other repos already audited in this program.
- **CVE exposure**: `CVE-2026-114509`, critical, in range at the locked framework version — see Dependency analysis above. Notably, a ready-to-merge fix (`PR #76`) already exists and is unmerged.
- **Secrets policy**: `.env.example`'s non-placeholder `APP_KEY` — see Config review. No confirmed live secret.

## CI/CD status

- **No `.github/` directory exists at all** — no CI workflow, no issue templates, no PR template, no Dependabot config. This repo starts Phase 4 (Dependabot config, standing decision 14), Phase 10 (CI pipeline), and Phase 15 (community standards) completely from zero, same as Phase 5 (no Docker baseline either, confirmed in Phase 2).
- No dependency/image vulnerability scanning exists anywhere (nothing to scan from — no CI, no images).

## Git hygiene

- Default branch is `master` (GitHub-confirmed via `gh repo view` this run), not `main` — standard rename candidate, **Gate 4** applies when Phase 4 reaches it. No `preview`/`test` branches exist yet — expected (standing decision 5's transition note), Phase 4 creates them.
- GitHub Discussions and Wiki are both **already enabled** (re-confirmed via `gh repo view` this run) — standing decision 13 compliant, no action needed.
- **12 open PRs, all bot-authored** (10 Renovate, 2 Dependabot) — see Dependency analysis above for the 3 individually diffed; the remaining 9 need Phase 4 triage / Phase 6 resolution.
- **0 open issues.**
- `.idea/` (11 files) was tracked despite no `.gitignore` rule covering it — fixed this phase (`git rm --cached`, `.gitignore` updated). This is the one working-tree change this phase made.
- No history-rewrite candidates found in this phase (working-tree secret scan clean); a full-history `gitleaks` pass is Phase 4's own remit and has not been run yet.
