# satellite-demo — ISS API (nestedflowtracker demo variant)

[![license](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

> **This is the instrumented demo fork** of [`adelinferaru/satellite`](https://github.com/adelinferaru/satellite).
> It adds [`nestedflowtracker`](https://github.com/adelinferaru/nestedflowtracker) spans and a
> `ContinueFlowTrace` middleware that continues an inbound W3C `traceparent`, so a request from
> [ISSWatch](https://github.com/adelinferaru/isswatch) renders as **one trace tree spanning both
> services**. Both apps write spans to a shared `flow` store (SQLite locally, MySQL in production).
> The clean, standalone API lives in the original `satellite` repo. **Deployment steps:
> see the ISSWatch README.**

A Laravel 13 API that returns the current International Space Station position
and computes the **slant-range distance** to it from any latitude/longitude.
Wraps [api.wheretheiss.at](https://wheretheiss.at) with a short response cache so
repeated polls stay well inside the upstream rate limit.

## Stack

- Laravel **13** on PHP **8.3+**.
- API + nestedflowtracker instrumentation (no frontend, no Node toolchain).
- Outbound HTTP via Laravel's `Http::` facade; cached for 1 s per NORAD id.

## Requirements

- PHP 8.3 recommended (8.2 minimum).
- Composer 2.7+.

## Install & run

```sh
composer install
cp .env.example .env
php artisan key:generate
php artisan serve
```

Dev server at `http://127.0.0.1:8000`. Health check at `/up`.

## Endpoints

| Method | Path | Returns |
|---|---|---|
| `GET` | `/api/satellites` | Satellites the upstream knows about (just the ISS today). |
| `GET` | `/api/satellite/{id?}` | Position, velocity, altitude, timestamp. Defaults to NORAD id 25544 (ISS). |
| `GET` | `/api/satellite/{id}/positions?timestamps=t1,t2,...` | Positions at 1–10 Unix timestamps (comma-separated). |
| `GET` | `/api/coordinates/{lat},{lon}` | Timezone / country info for the supplied coordinates (passthrough). |
| `GET` | `/api/distance/{lat},{lon}` | Slant-range distance from the supplied ground point to the ISS, in km. |

### Response shape

Successful calls wrap data in an envelope:

```json
{
  "result": 1,
  "data": { ... }
}
```

Invalid input → **422** with an `errors` map:

```json
{ "result": 0, "errors": { "lat": ["The lat must be between -90 and 90."] } }
```

Upstream failure → **502**:

```json
{ "result": 0, "message": "..." }
```

### Examples

```sh
# Current ISS position
curl http://127.0.0.1:8000/api/satellite

# Distance from New York to the ISS (slant range, km)
curl http://127.0.0.1:8000/api/distance/40.7128,-74.0060

# Reject invalid coords (returns 422)
curl -i http://127.0.0.1:8000/api/distance/999,999

# ISS positions at three given Unix timestamps
curl "http://127.0.0.1:8000/api/satellite/25544/positions?timestamps=1672531200,1672531260,1672531320"
```

`data.distance` is the **slant range** (line-of-sight 3D distance through space),
not great-circle ground distance. It accounts for the ISS's actual altitude
returned by the upstream (~408 km).

## Tests

```sh
php artisan test
```

Unit tests cover the great-circle and slant-range math plus the coordinate
validators. Feature tests exercise every route via `Http::fake()` so the suite
never touches `api.wheretheiss.at`.

## History

Originally written on Laravel 5.7 with a Vue 2 + Bootstrap 4 frontend, then
rewritten onto Laravel 12 as a pure API. See `UPGRADE.md` for the staged plan
and `PROGRESS.md` for the decision log from the rewrite.

## License

[MIT](LICENSE)

## Author

Feraru Ioan Adelin · `adelin.feraru@gmail.com`
