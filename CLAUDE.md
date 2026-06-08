# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this project is

A Laravel 12 API that wraps `api.wheretheiss.at` to expose the current International Space Station position and compute slant-range distance from any latitude/longitude to the ISS. Originally a Laravel 5.7 + Vue homework project; rewritten onto Laravel 12 as a pure API. See `UPGRADE.md` for the staged rewrite plan and `PROGRESS.md` for the decision log captured during the rewrite.

## Toolchain

System `PATH` on this machine resolves to **PHP 7.2.11**; the project requires **PHP 8.2+**. PHP 8.3 is installed at `C:\laragon\bin\php\php-8.3.19-nts-Win32-vs16-x64\php.exe`. Either fix `PATH` via Laragon (PHP → Version → 8.3.19) or invoke that binary explicitly.

Composer 2.8.9, Node not required (no frontend toolchain).

## Commands

- `composer install` — install dependencies.
- `cp .env.example .env && php artisan key:generate` — first-time setup.
- `php artisan serve` — dev server at `http://127.0.0.1:8000`. Health endpoint at `/up`.
- `php artisan test` — runs the full PHPUnit suite via Laravel's test wrapper. Add `--filter=method_name` for a single test, or pass a path.
- `php artisan route:list --path=api` — sanity check after editing routes.

## Architecture

Five files cover the entire feature. Read in this order:

1. **`app/Repositories/ISSContract.php`** — interface for the upstream API: `getSatellites()`, `getSatelliteId($id)`, `getSatelliteIdPositions($id, $timestamps)`, `getCoordinates($lat, $lon)`. All return an envelope `['result' => 1|0, 'data' => ..., 'message' => ?]`.
2. **`app/Repositories/ISSGateway.php`** — implementation built on Laravel's `Http::` facade against `https://api.wheretheiss.at/v1/`. `getSatelliteId` is wrapped in `Cache::remember(..., 1s)` keyed by NORAD id to stay under the upstream rate limit.
3. **`app/Traits/Measurable.php`** — `geoDistance()` (great-circle, km), `slantRangeDistance()` (3D distance to satellite at altitude, used for distance-to-ISS), `isValidCoordinate()` (numeric range check), plus three legacy regex validators kept for string callers.
4. **`app/Http/Controllers/IssController.php`** — one controller for all routes. Type-hints the `ISSContract` interface (mockable in tests via `Http::fake()` because of the gateway-level fake interception, or via direct `$this->app->instance()` if you want to swap the whole gateway).
5. **`routes/api.php`** — five GET endpoints (satellites list, single satellite, multi-timestamp positions, coordinates passthrough, slant-range distance). The `apiPrefix: 'api'` is set in `bootstrap/app.php`'s `withRouting(...)`.

The `ISSContract` → `ISSGateway` binding lives in `AppServiceProvider::register()` as a singleton.

## Important behaviors

- **Distance is slant range, not ground track.** `getDistance` accounts for the ISS's actual altitude (from the upstream `altitude` field, fallback 408 km). Response includes `data.measurement = "slant_range"` and an `iss` block so clients can verify.
- **Validation returns 422 with an `errors` map.** Invalid path-param coordinates don't return a quiet `{result:0}`.
- **Upstream failures return 502.** Don't dress them up as 200.
- **Position cache is 1 second.** A `getSatelliteId` call within 1 second of a previous one returns cached data; verified in `IssEndpointsTest::test_satellite_position_is_cached_for_one_second`.

## L11+ structural notes

This is the slimmed-down skeleton — there is **no `app/Http/Kernel.php`** and **no `app/Console/Kernel.php`**. Middleware, exception handling, route file registration, scheduled commands, and health checks all configure fluently in `bootstrap/app.php` via `withRouting()`, `withMiddleware()`, `withExceptions()`, `withSchedule()`. `routes/api.php` is opt-in; the project enables it explicitly in `bootstrap/app.php`. Don't remove that line.

## Tests

- `tests/Unit/MeasurableTest.php` — `geoDistance` and `slantRangeDistance` math plus the lat/long validators.
- `tests/Feature/IssEndpointsTest.php` — all four routes via `Http::fake()` with `Http::preventStrayRequests()` guarding against accidental upstream hits.

`phpunit.xml` already sets `CACHE_STORE=array`, so `Cache::remember` is test-isolated.

## SQLite default

`.env.example` ships with `DB_CONNECTION=sqlite`. Laragon's PHP doesn't load `pdo_sqlite` by default, so `php artisan migrate` warns. The app has no DB needs; either ignore the warning or change `.env` to `DB_CONNECTION=null` locally. Don't bother enabling `pdo_sqlite`.

## `composer dev` script

The L12 skeleton's `composer dev` script tries to run `npm run dev` and `php artisan pail` alongside `artisan serve`. With no npm in this build it fails. **Use `php artisan serve` directly** as the canonical dev command.
