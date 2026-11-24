# Architecture

> Written during Phase 3 (audit/plan). Describes the system **as it exists today** on `master` (`8148b14`, pushed 2026-11-13) — no application-code changes were made to produce this document. The only working-tree changes in this phase are the IDE-artifact removal (`.idea/` untracked, `.gitignore` updated) required as the first action on any repo per `CLAUDE.md`.

## Overview

`hascode-task2-laravel` is a single-tier Laravel 10 REST API: a small CRUD service over one business resource (`Hash` — arbitrary text plus two derived SHA1 digests), fronted by Laravel Passport token authentication. There is no frontend application; `webpack.mix.js`/`package.json`'s npm toolchain exists only to compile `resources/js`/`resources/sass` into the `public/js`/`public/css` assets Laravel's default welcome page would use — it has no relationship to the API itself. No other services, no queue workers actually wired up despite Redis being configured, no cross-repo links (confirmed in Phase 2, re-confirmed here: zero git/URL dependencies, zero shared packages).

```
┌───────────────────┐        HTTP (Bearer token)        ┌──────────────────────────┐
│   API client       │ ─────────────────────────────▶   │  Laravel 10 app (root)   │
│   (unspecified —   │   Authorization: Bearer <token>    │  routes/api.php          │
│   no SDK/fe exists) │                                    │  → Controllers → Models  │
└───────────────────┘                                    └────────────┬─────────────┘
                                                                       │ Eloquent
                                                                       ▼
                                                              ┌──────────────────┐
                                                              │ PostgreSQL        │
                                                              │ (per .env.example;│
                                                              │  no compose/      │
                                                              │  container exists)│
                                                              └──────────────────┘
```

No Dockerfile, `docker-compose.yml`, or Makefile exists anywhere in the repo — "PostgreSQL" above is only the `.env.example`'s declared `DB_CONNECTION`, never an actually-provisioned service. Phase 5 builds the entire container baseline from scratch here, the same situation `deps/hascode-task2-laravel.md` already flagged for the Docker side of Phase 2.

## Backend module diagram

```
routes/api.php
  ├─ POST /api/register                           [no auth]      → AuthController::register
  ├─ POST /api/login                               [no auth]      → AuthController::login
  ├─ GET  /api/user                                [auth:api]     → closure, returns $request->user()
  └─ Route::apiResource('/hash', HashController)   [auth:api]     → HashController (index/store/show/update/destroy)

app/Http/Controllers/API/AuthController
  ├─ register()  — validate name/email/password → bcrypt → User::create → Passport createToken
  └─ login()     — validate email/password → auth()->attempt($request->all()) → Passport createToken

app/Http/Controllers/API/HashController
  ├─ index()    — Hash::all(), wrapped in HashResource::collection (no pagination)
  ├─ store()    — validates `data` only; computes sha1(data) as `hash`; computes `hash0` via a
  │                self-dedup lookup (showData()) — first-ever occurrence of a given `data` value
  │                gets hash0 = sha1(data); a repeat occurrence should inherit the *first* row's
  │                hash0 (see Risks — this inheritance path is broken)
  ├─ showData() — helper, NOT a route: looks up existing rows by exact `data` match; returns the
  │                int 0 (no prior row) or the result of calling response() on a Collection (a
  │                Response object, not an array) — the caller then indexes into this object with
  │                array syntax, which is a defect (see Risks)
  ├─ show($hash)       — looks up Hash by **hash0** column (not `id`, not Eloquent route-model
  │                       binding — $hash is untyped, so Laravel never attempts implicit binding here)
  ├─ update(Request, Hash $hash) — Eloquent implicit binding by **id** (default route key, no
  │                       getRouteKeyName() override on the Hash model) — same {hash} route segment,
  │                       different lookup semantics than show() (see Risks)
  └─ destroy(Hash $hash)         — same implicit id-binding as update()

app/Http/Resources/HashResource — explicit field allowlist: id, data, hash, created_at, updated_at.
  `hash0` is deliberately not exposed to the client, even though show()'s own lookup key is hash0 —
  a client cannot discover the value it needs to call GET /api/hash/{hash} from the store() response
  it is given (see Risks).

app/Models/User — Laravel\Passport\HasApiTokens, standard Authenticatable.
app/Models/Hash  — $fillable = [data, hash, hash0]; no relations, no casts, no route key override.
```

## Data model

```sql
hashes
  id          bigint       PRIMARY KEY, auto-increment
  data        varchar
  hash        varchar      -- sha1(data), always
  hash0       varchar      -- "first occurrence" dedup anchor, see Risks
  created_at / updated_at

users                       -- Laravel default scaffold, actually used (registration/login)
password_resets              -- migrated, never referenced by any controller/route
failed_jobs                   -- migrated, no queue worker ever configured to populate it
personal_access_tokens       -- Passport's own token storage
```

No foreign keys anywhere — `hashes` has no relation to `users` despite auth existing; any authenticated user can read/update/delete any row, including rows created by a different user (no ownership column, no authorization check — see Risks and `docs/TECHNICAL_DEBT.md`). No index beyond the primary key: `data`/`hash0` lookups (`store()`'s dedup check, `show()`'s route lookup) are both full table scans — immaterial at today's likely-small row count, a real cost only if this ever needed to scale (see `docs/IMPROVEMENT_ROADMAP.md`'s distributed-deployment note).

## API contract (as implemented)

| Method | Path | Auth | Request body | Response | Notes |
|---|---|---|---|---|---|
| POST | `/api/register` | none | `name, email, password` | `{user, access_token}` | `password` min:6, `email` unique |
| POST | `/api/login` | none | `email, password` | `{access_token}` or `{message}` (200 either way — see Risks) | passes the **entire** request body to `auth()->attempt()`, not just the two validated fields |
| GET | `/api/user` | Bearer | — | the authenticated `User` model, unfiltered | no resource transformer — raw model serialization |
| GET | `/api/hash` | Bearer | — | `{hashes: [...]}` | `Hash::all()`, no pagination, no per-user scoping |
| POST | `/api/hash` | Bearer | `data` (min:5) | `{hash, message}` | no per-user ownership recorded |
| GET | `/api/hash/{hash}` | Bearer | — | `{item}` or `404 {message}` | `{hash}` must be a **hash0** value; `hash0` is never returned by `store()`'s response, so a client has no documented way to obtain it (see Risks) |
| PUT/PATCH | `/api/hash/{hash}` | Bearer | `data` (min:5) | `{hash, message}` | `{hash}` here must be the row's **id** (implicit binding), not a hash0 value — same route parameter name, different required value than `show()` |
| DELETE | `/api/hash/{hash}` | Bearer | — | `{message}` | same id-based binding as `update()` |

## Configuration / environment

- `.env.example` is placeholder-style for every credential field except `APP_KEY`, which is a real base64-encoded 32-byte value (not an obvious placeholder like an empty string or `SomeRandomString32CharsLong`). Flagged as a community-standards hygiene gap in `docs/TECHNICAL_DEBT.md` — not escalated as a Gate 1 live-secret finding: this is a generated-looking `.env.example` key with no evidence it is ever used outside local dev, and `gitleaks dir . --redact` (this phase) found no leaks.
- `DB_CONNECTION=pgsql` in `.env.example`, but the repo's own `README.md` also calls out PostgreSQL explicitly — consistent, unlike some sibling repos in this program where the example and the real stack disagree.
- No `config/cors.php` published; CORS behavior for any browser-based client depends on undocumented framework defaults — not verified at runtime in this phase (no container exists yet to verify it in).
- Redis is configured (`REDIS_HOST`/`REDIS_PORT`/`REDIS_PASSWORD`) but nothing in `app/` uses the `Redis` facade or a Redis-backed cache/queue/session driver — `CACHE_DRIVER=file`, `SESSION_DRIVER=file`, `QUEUE_CONNECTION=sync` in the same file. Redis config is present but inert.

## Risks (structural/correctness, confirmed by reading the code — not assumed)

1. **`HashController::store()`'s dedup logic has a live defect.** `showData()` returns either the integer `0` or the result of `response([$hash])` (a `Response` object). `store()` then does `$oldHash['hash0']` on whatever `showData()` returned — array-indexing a `Response` object is not how Laravel's `Response` class behaves; this line would not return the intended `hash0` string on the "data already exists" branch. Confirmed by reading the method bodies; not executed/reproduced in this phase (Phase 3 is read-only).
2. **`update()` references an unvalidated key.** `$request->validate(['data' => 'required|min:5'])` only ever populates `$validator['data']`; the next line reads `$validator['hash0']` — a key that was never part of the validated array. This would raise an undefined-array-key warning/error on every call to `PUT /api/hash/{id}`, not a hypothetical edge case.
3. **The `{hash}` route parameter means two different things depending on HTTP verb.** `GET /api/hash/{hash}` looks the value up against the `hash0` column; `PUT`/`PATCH`/`DELETE /api/hash/{hash}` resolve it via Eloquent's default implicit binding against `id` (the `Hash` model never overrides `getRouteKeyName()`). A client following the `show()` response has no way to construct a working `update()`/`destroy()` URL from it.
4. **`hash0` is never returned to the client**, by `HashResource`'s explicit field list, yet it is the only value `show()` accepts. Combined with #3, the practical effect is: the only way to reliably retrieve or mutate a specific row today is by its numeric `id`, which the client also isn't told unless it already has the full `HashResource` payload from `store()`/`index()` (which does include `id`).
5. **`login()` passes the full request body into `auth()->attempt($request->all())`**, not just the two validated credential fields — any additional field in the POST body becomes part of the `WHERE` clause Laravel's auth guard builds. Not a demonstrated exploit in this review, but a deviation from the safer pattern (passing only the validated `email`/`password` pair) that Phase 9 should tighten.
6. **No per-user ownership or authorization on `hashes`.** Any authenticated user can list, read, update, or delete any other user's `Hash` rows — there is no `user_id` column and no policy/gate anywhere in the codebase referencing `Hash`.
7. **`use Hash;` in `AuthController.php`** imports the PHP root-namespace `Hash` class (never actually used in the file body) while this app's own primary business model is also named `Hash` (`App\Models\Hash`) — a confusing naming collision, not a functional bug today only because the import is unused.

## Testing (as implemented)

- `tests/Feature/AuthTest.php`, `tests/Feature/HashTest.php`, `tests/Unit/ExampleTest.php` — read, not executed, in this phase.
- `HashTest::test_find_hash()` calls `$this->test_create_hash()->hash` — but `test_create_hash()` has no `return` statement (it ends on an assertion), so this expression evaluates `null->hash`, which is a hard error, not a working helper-method chain. This test could not have been passing as written; whether it was ever actually run and whether CI (there is none) would have caught it is unconfirmed — read-only finding, not reproduced by actually running the suite in this phase.
- `tests/CreatesApplication.php`/`tests/TestCase.php` are the stock Laravel scaffolding, unmodified.

## Security posture (as implemented, static read only — no live scan beyond the working-tree secret scan below)

- Redacted secret scan of the working tree (`gitleaks dir . --redact --no-banner`, this phase): exit 0, "no leaks found."
- `laravel/sanctum` is declared (`composer.json`) but confirmed unused by grep (`Sanctum`, `HasApiTokens` from the Sanctum namespace, `sanctum` guard/middleware — zero hits outside the vendor-irrelevant namespace collision already noted); Passport is the real, active guard.
- Authentication exists (Passport) but authorization does not (see Risk #6 above) — the single biggest concrete security gap, same shape of finding as other repos already audited in this program.
- Dependency vulnerabilities, including the critical `CVE-2026-114509` (Laravel TokenGuard auth bypass) at the locked framework version: fully quantified in `projects/info/deps/hascode-task2-laravel.md` and summarized in `docs/TECHNICAL_DEBT.md` — not re-run here, cited per the orchestrator's Phase 2→3 data flow.

## Phase 8/8B/9 — not yet run

This document describes Phase 3's as-is snapshot only. No architecture principles have been applied yet (that is Phase 8's job, after Phase 6/7 land); no new features have been proposed (Phase 8B); no hardening has been done (Phase 9). See `docs/IMPROVEMENT_ROADMAP.md` for how each later phase is expected to land on this specific codebase.
