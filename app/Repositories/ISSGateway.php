<?php

namespace App\Repositories;

use AdelinFeraru\NestedFlowTracker\Laravel\Facades\Flow;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class ISSGateway implements ISSContract
{
    private const DEFAULT_BASE = 'https://api.wheretheiss.at/v1/';

    public function __construct(
        private readonly string $baseUrl = self::DEFAULT_BASE,
        private readonly int $timeoutSeconds = 5,
        private readonly int $cacheSeconds = 1,
    ) {
    }

    public function getSatellites(): array
    {
        return $this->call('satellites');
    }

    public function getSatelliteId(int $id = 25544): array
    {
        // Short cache to respect the upstream rate limit (350 req / 5 min) and to
        // absorb slow upstream TLS handshakes: while warm, polls return instantly.
        // The ISS moves ~7.6 km/s, so a few seconds' staleness is invisible on a
        // world map. Tunable via ISS_CACHE_SECONDS (config services.iss.cache_seconds).
        //
        // NB: use a *relative* integer TTL (evaluated when the value is stored,
        // after the slow call returns) — an absolute now()->addSeconds() expiry
        // computed up front would already be in the past by write time. Only
        // successful fixes are cached, so a transient upstream failure isn't sticky.
        $key = "iss.satellite.{$id}";

        if (($cached = Cache::get($key)) !== null) {
            return $cached;
        }

        $result = $this->call("satellites/{$id}");

        if (($result['result'] ?? 0) === 1) {
            Cache::put($key, $result, $this->cacheSeconds);
        }

        return $result;
    }

    public function getSatelliteIdPositions(int $id, array $timestamps = []): array
    {
        if ($timestamps === []) {
            return $this->failure('At least one timestamp is required.');
        }

        return $this->call("satellites/{$id}/positions", [
            'timestamps' => implode(',', $timestamps),
        ]);
    }

    public function getCoordinates(float $lat, float $lon): array
    {
        return $this->call("coordinates/{$lat},{$lon}");
    }

    private function call(string $path, array $query = []): array
    {
        return Flow::span("fetch wheretheiss.at: {$path}", function ($span) use ($path, $query) {
            $span->context = ['path' => $path, 'query' => $query, 'upstream' => $this->baseUrl];

            try {
                $response = Http::baseUrl($this->baseUrl)
                    ->timeout($this->timeoutSeconds)
                    ->acceptJson()
                    ->get($path, $query)
                    ->throw();

                $span->result = ['status' => $response->status()];

                return [
                    'result' => 1,
                    'data' => $response->json(),
                ];
            } catch (Throwable $e) {
                $span->result = ['error' => $e->getMessage()];

                return $this->failure($e->getMessage());
            }
        });
    }

    private function failure(string $message): array
    {
        return [
            'result' => 0,
            'data' => null,
            'message' => $message,
        ];
    }
}
