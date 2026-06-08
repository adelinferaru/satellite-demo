# Laravel 5.7 → 12 Upgrade Roadmap

## Context

- Current: Laravel **5.7** on PHP **7.1+**, Vue 2 + Bootstrap 4 + Laravel Mix, PHPUnit 7.
- Target: Laravel **12** on PHP **8.2+**, Vue 3 + Vite, PHPUnit 11.
- Application surface is tiny: 1 controller (4 actions), 1 gateway + contract, 1 trait, 4 API routes, 1 view, 2 Vue components. The unused `make:auth` scaffolding adds noise but no real code.

## Strategy: rewrite onto a Laravel 12 skeleton, don't upgrade in place

A traditional 5.7 → 12 upgrade would mean walking 5.8 → 6 → 7 → 8 → 9 → 10 → 11 → 12, each with its own breaking changes (Symfony bumps, helper-function removals, route namespace flip in 8, Carbon 2, Mix → Vite in 9, full structural overhaul in 11, etc.). For an app with ~150 lines of real domain code, that's wasted work. A fresh `laravel new` and porting the four pieces is faster, cleaner, and uses the modern slimmed-down structure (no `Http/Kernel.php`, no `Console/Kernel.php`, fluent middleware/exception config in `bootstrap/app.php`).

The plan below assumes the rewrite path. An incremental option is sketched at the end.

---

## Phase 0 — Prerequisites ✅

- [x] PHP **8.3.19** active on PATH (Laragon).
- [x] Composer **2.8.9**.
- [x] Node **22.15.1**.
- [x] Working branch `upgrade/laravel-12` created.

## Decisions locked in

- **Scaffold location:** `_next/` subfolder inside this repo.
- **Frontend:** pure API, no UI. Drops **Phase 3** entirely; the existing `iss.blade.php` and the two Vue components will not be ported. Anyone wanting the dashboard can build a separate frontend against the JSON endpoints.

## Phase 1 — Scaffold a fresh Laravel 12 project

1. `composer create-project laravel/laravel _next` from the repo root. No starter kit (API-only).
2. Trim the skeleton: delete `resources/views/welcome.blade.php`, remove the `/` web route, drop frontend deps from `package.json` (Vite, Tailwind, etc.) — none of it is needed.
3. Enable the API route file: in `bootstrap/app.php`, pass `api: __DIR__.'/../routes/api.php'` to `withRouting(...)`. In L11+ this is not on by default.
4. Confirm `php artisan serve` boots the empty skeleton and `/up` returns 200 before porting anything.

## Phase 2 — Port the domain code

These files barely depend on Laravel internals, so the port is mostly copy-paste with namespace/type adjustments:

| Source | Destination | Changes |
|---|---|---|
| `app/Repositories/ISSContract.php` | same path | Add return types (`array`) and parameter types. PHP 8 features fine. |
| `app/Repositories/ISSGateway.php` | same path | Replace direct Guzzle with Laravel's `Http::` facade. Add property types, constructor promotion, typed returns. |
| `app/Traits/Measurable.php` | same path | Add scalar/return types. Validation regex stays as-is. |
| `app/Http/Controllers/IssController.php` | same path | Extend the L12 base controller. Replace dynamic `Request $request = null` pattern with cleaner action signatures. Return `JsonResponse`. |
| `routes/api.php` | same path | Route definitions copy over. In L11+ you must opt into `api.php` via `withRouting(api: __DIR__.'/../routes/api.php')` in `bootstrap/app.php` — it's not loaded by default. |
| `routes/web.php` | same path | Strip down to nothing (or remove). API-only build. |
| ~~`resources/views/iss.blade.php`~~ | — | **Dropped** with the frontend. |

**Bind the repository:** in 5.7 the controller type-hints `ISSGateway` (concrete class) so no binding is needed. In the rewrite, decide whether to bind `ISSContract` → `ISSGateway` in `AppServiceProvider::register()` and type-hint the interface in the controller. Cleaner and matches what the contract was clearly intended for.

**Delete the unused auth scaffolding** — `Auth/*Controller.php`, `RedirectIfAuthenticated`, the password reset views, `users` and `password_resets` migrations. They're not referenced. If auth comes back later, use Breeze (`composer require laravel/breeze --dev`).

## Phase 3 — Frontend port — **SKIPPED** (API-only build)

The legacy `resources/views/iss.blade.php` view and the `IssPosition.vue` / `IssDistance.vue` components are not being ported. Laravel Mix, Vue 2, Bootstrap 4, jQuery, popper.js, cross-env, resolve-url-loader, and sass-loader all leave with them.

## Phase 4 — Dependency refresh

| Package | 5.7 version | 12 version |
|---|---|---|
| `laravel/framework` | `5.7.*` | `^12.0` |
| `guzzlehttp/guzzle` | `~6.0` | Pulled transitively by Laravel core when using `Http::`; no direct dep needed |
| `fideloper/proxy` | `^4.0` | **remove** — merged into Laravel core (`TrustProxies` is built in) |
| `laravel/tinker` | `^1.0` | `^2.9` |
| `phpunit/phpunit` | `^7.0` | `^11.0` (Laravel 12 supports both 10 and 11) |
| `mockery/mockery` | `^1.0` | `^1.6` |
| `fzaninotto/faker` | `^1.4` | **remove** — replaced by `fakerphp/faker` (Laravel pulls this transitively) |
| `nunomaduro/collision` | `^2.0` | `^8.0` |
| `beyondcode/laravel-dump-server` | `^1.0` | **remove** — `dump-server` is built into Laravel 9+ via `php artisan dump-server` |

## Phase 5 — Tests

The repo has no domain tests — only the default `tests/Feature/ExampleTest.php` and `tests/Unit/ExampleTest.php`. Use the rewrite as a chance to add what the original README's TBD list called out:

- **Unit test** for `Measurable::geoDistance` against known great-circle distances (e.g., NYC ↔ London ≈ 5570 km).
- **Unit test** for the lat/long validators (boundary values: ±90, ±180, malformed inputs).
- **Feature test** for `/api/distance/{lat},{lon}` with a mocked `ISSContract` binding so tests don't hit `api.wheretheiss.at`.
- **Feature test** that `/api/satellite/{id?}` defaults to `25544`.

Laravel 12 supports Pest out of the box if a more modern test style is wanted, but PHPUnit 11 is fine.

## Phase 6 — Behavior fixes worth doing during the rewrite

Pre-existing issues in the 5.7 code; cheaper to fix once during the port than after:

1. **Distance ignores ISS altitude.** `geoDistance` is great-circle on Earth's surface. The ISS is ~400 km up. Either:
   - Rename to `groundTrackDistance` for honesty, **or**
   - Add a separate `slantRangeDistance($lat, $lon, $issLat, $issLon, $altKm = 408)` using the law of cosines on a sphere of radius `R + altKm`. The wheretheiss.at API returns the actual altitude in the `altitude` field — use it.
2. **`getSatelliteIdPositions` is stubbed.** Either implement it (the upstream endpoint exists) or remove from the contract.
3. **No validation in `calculateDistance` returns generic `{result: 0}`.** Use a FormRequest with `required|numeric|between:-90,90` and `between:-180,180` so the client gets useful 422 responses.
4. ~~**Outbound HTTP**: replace direct Guzzle with Laravel's `Http::` facade.~~ **Moved into Phase 2** — done as part of the gateway port.
5. **Cache ISS position** for ~1s. The upstream API rate-limits at 1 req/s; the legacy frontend's Refresh button + form submission could collide. Less urgent in the API-only build but still worth doing.

## Phase 7 — Cutover

1. Move the ported files back into the original repo (or rename the scaffold directory into place).
2. Update `readme.md`: PHP 8.3, Node 20, `npm install && npm run dev` instead of Mix.
3. Smoke test:
   - `composer install && php artisan key:generate`
   - `npm install && npm run build`
   - `php artisan serve` → load `/`, click Refresh, submit the distance form.
4. `php artisan test` should be green.

---

## Alternative: incremental in-place upgrade

If a full rewrite is off the table, the sequence is:

`5.7 → 5.8 → 6.x → 7.x → 8.x → 9.x → 10.x → 11.x → 12.x`

Each hop = update `composer.json`, run `composer update`, follow that release's upgrade guide. Painful hops for this codebase:

- **5.8 → 6.0**: global `array_*` / `str_*` helpers removed. None used here, so easy.
- **7 → 8**: `RouteServiceProvider` no longer auto-prefixes controllers with namespace. Either keep the prefix or switch routes to FQCN imports.
- **8 → 9**: Symfony 6, PHP 8.0 minimum, Flysystem 3. Pagination view defaults change.
- **9 → 10**: PHP 8.1 minimum, Mix → Vite migration recommended.
- **10 → 11**: Major structural change — `app/Http/Kernel.php`, `app/Console/Kernel.php`, default middleware files all removed; replaced by fluent config in `bootstrap/app.php`. `routes/api.php` and `routes/channels.php` no longer registered unless opted in.
- **11 → 12**: relatively small.

Estimated effort: ~2× the rewrite, because almost every hop touches structural files that would be replaced anyway.

---

## Estimated effort

- Rewrite path: **0.5–1 day** for a developer familiar with Laravel 12.
- Incremental path: **2–4 days** including time spent reading each upgrade guide.
