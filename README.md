# hascode-task2-laravel

A Laravel 10 REST API with Hash CRUD operations and token-based authentication via Passport.

## Stack

| Layer | Technology | Version |
|-------|-----------|---------|
| Language | PHP | ^8.0.2 |
| Framework | Laravel | 10.0 |
| Auth | Laravel Passport | ^10.4 |
| Database | PostgreSQL | Configured in `.env.example` |
| Testing | PHPUnit | ^9.5 |
| Queue | Redis | Configured |

## Architecture

```
api/
├── app/
│   ├── Http/
│   │   ├── Controllers/API/
│   │   │   ├── AuthController.php  — register, login (token auth)
│   │   │   └── HashController.php  — Hash CRUD (index, store, show, update, destroy)
│   │   └── Resources/
│   │       └── HashResource.php    — API resource transformer
│   ├── Models/
│   │   ├── Hash.php
│   │   └── User.php
│   └── Providers/
│       ├── AppServiceProvider.php
│       └── ... (default Laravel providers)
├── config/         — framework + app config
├── database/
│   ├── migrations/ — users, password_resets, failed_jobs, personal_access_tokens, hashes
│   └── seeders/
├── routes/
│   └── api.php     — API routes
├── tests/
│   ├── Feature/    — AuthTest, HashTest
│   └── Unit/       — ExampleTest
├── public/         — entry point, assets
└── vendor/
```

The app lives under `api/`. All API routes are prefixed with `/api`.

## API Routes

| Method | Route | Auth | Description |
|--------|-------|------|-------------|
| POST | /api/register | none | Register new user, returns access_token |
| POST | /api/login | none | Authenticate, returns access_token |
| GET | /api/hash | auth:api | List all hashes |
| POST | /api/hash | auth:api | Create hash |
| GET | /api/hash/{hash} | auth:api | Find hash by hash0 |
| PUT | /api/hash/{hash} | auth:api | Update hash |
| DELETE | /api/hash/{hash} | auth:api | Delete hash |
| GET | /api/user | auth:api | Authenticated user info |

## Hash Model

Hashes store arbitrary `data` and produce SHA1 hashes. The `hash` field stores the SHA1 of `data`. The `hash0` field stores an initial hash reference (used for lookup).

| Field | Type | Description |
|-------|------|-------------|
| id | bigint | Primary key |
| data | string | Input data |
| hash | string | SHA1 of data |
| hash0 | string | Initial hash reference |
| timestamps | — | Created/updated at |

## Authentication

Uses **Laravel Passport** for API token authentication. The `User` model uses `Laravel\Passport\HasApiTokens`.

- Register: validates name/email/password, creates user with bcrypt password, generates Passport token
- Login: validates email/password, calls `auth()->attempt()`, generates Passport token
- Token is returned as `access_token` in JSON response

## Setup

```bash
# Clone
git clone https://github.com/RasimAghayev/hascode-task2-laravel.git
cd hascode-task2-laravel/api

# Install dependencies
composer install
npm install

# Environment
cp .env.example .env
php artisan key:generate

# Database (PostgreSQL by default)
php artisan migrate

# Passport
php artisan passport:install

# Run
php artisan serve
```

## Testing

```bash
php artisan test
# or
vendor/bin/phpunit
```

Test coverage:
- `AuthTest`: registration and login flows
- `HashTest`: hash creation and lookup

## Notes

- `laravel/sanctum` is in `composer.json` but unused — only Passport is active
- `.env.example` contains hardcoded `APP_KEY` and `APP_DEBUG=true`
- `HashResource` returns raw model columns via `parent::toArray()` (no field filtering)
- `HashController::store()` contains debug code (`print_r` + `die()`) in production path
