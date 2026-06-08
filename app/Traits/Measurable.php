<?php

namespace App\Traits;

trait Measurable
{
    private const EARTH_RADIUS_KM = 6371.0;

    public function geoDistance(float $latFrom, float $lonFrom, float $latTo, float $lonTo): float
    {
        $rad = M_PI / 180;
        $theta = $lonFrom - $lonTo;
        $dist = sin($latFrom * $rad) * sin($latTo * $rad)
            + cos($latFrom * $rad) * cos($latTo * $rad) * cos($theta * $rad);

        // Clamp against float-precision overshoot; acos of >1 returns NaN.
        $dist = max(-1.0, min(1.0, $dist));

        return acos($dist) / $rad * 60 * 1.852;
    }

    /**
     * Slant-range distance from a ground point to a satellite at altitude $altKm,
     * whose nadir (subsatellite point) is at ($latTo, $lonTo). Law of cosines on
     * the triangle: Earth center, ground point, satellite.
     */
    public function slantRangeDistance(
        float $latFrom,
        float $lonFrom,
        float $latTo,
        float $lonTo,
        float $altKm,
    ): float {
        $groundTrackKm = $this->geoDistance($latFrom, $lonFrom, $latTo, $lonTo);
        $centralAngle = $groundTrackKm / self::EARTH_RADIUS_KM;
        $satOrbitRadius = self::EARTH_RADIUS_KM + $altKm;

        return sqrt(
            self::EARTH_RADIUS_KM ** 2
            + $satOrbitRadius ** 2
            - 2 * self::EARTH_RADIUS_KM * $satOrbitRadius * cos($centralAngle),
        );
    }

    public function isValidCoordinate(mixed $lat, mixed $lon): bool
    {
        if (! is_numeric($lat) || ! is_numeric($lon)) {
            return false;
        }

        return $lat >= -90.0 && $lat <= 90.0
            && $lon >= -180.0 && $lon <= 180.0;
    }
}
