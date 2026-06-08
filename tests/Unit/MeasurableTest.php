<?php

namespace Tests\Unit;

use App\Traits\Measurable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MeasurableTest extends TestCase
{
    private object $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new class {
            use Measurable;
        };
    }

    public function test_geo_distance_returns_near_zero_for_identical_points(): void
    {
        // Spherical law of cosines loses precision near 0 from float roundoff;
        // sub-meter is the practical floor (clamp keeps it non-NaN).
        $this->assertEqualsWithDelta(
            0.0,
            $this->subject->geoDistance(40.0, -74.0, 40.0, -74.0),
            0.001,
        );
    }

    public function test_geo_distance_new_york_to_london(): void
    {
        // JFK (40.6413, -73.7781) -> LHR (51.4700, -0.4543)
        // Expected great-circle ~5538 km. Loose tolerance because the trait
        // uses spherical-law-of-cosines, not haversine.
        $km = $this->subject->geoDistance(40.6413, -73.7781, 51.4700, -0.4543);
        $this->assertEqualsWithDelta(5538.0, $km, 30.0);
    }

    public function test_geo_distance_is_symmetric(): void
    {
        $a = $this->subject->geoDistance(40.6413, -73.7781, 51.4700, -0.4543);
        $b = $this->subject->geoDistance(51.4700, -0.4543, 40.6413, -73.7781);
        $this->assertEqualsWithDelta($a, $b, 1e-6);
    }

    public function test_slant_range_directly_overhead_equals_altitude(): void
    {
        // Ground point at the satellite's nadir: slant range == altitude.
        $km = $this->subject->slantRangeDistance(0.0, 0.0, 0.0, 0.0, 408.0);
        $this->assertEqualsWithDelta(408.0, $km, 0.001);
    }

    public function test_slant_range_grows_monotonically_with_separation(): void
    {
        $overhead = $this->subject->slantRangeDistance(0.0, 0.0, 0.0, 0.0, 408.0);
        $near = $this->subject->slantRangeDistance(0.0, 0.0, 0.0, 10.0, 408.0);
        $far = $this->subject->slantRangeDistance(0.0, 0.0, 0.0, 60.0, 408.0);

        $this->assertLessThan($near, $overhead);
        $this->assertLessThan($far, $near);
        // Sanity: slant range stays under line-of-sight through the orbital
        // diameter even at long horizon separations.
        $this->assertLessThan(2 * (6371.0 + 408.0), $far);
    }

    #[DataProvider('validCoordinates')]
    public function test_is_valid_coordinate_accepts_valid(mixed $lat, mixed $lon): void
    {
        $this->assertTrue($this->subject->isValidCoordinate($lat, $lon));
    }

    public static function validCoordinates(): array
    {
        return [
            'origin' => [0.0, 0.0],
            'positive' => [45, 120],
            'negative decimals' => [-45.5, -73.99],
            'max positive bounds' => [90, 180],
            'max negative bounds' => [-90, -180],
            'numeric strings' => ['40.7', '-74.0'],
        ];
    }

    #[DataProvider('invalidCoordinates')]
    public function test_is_valid_coordinate_rejects_invalid(mixed $lat, mixed $lon): void
    {
        $this->assertFalse($this->subject->isValidCoordinate($lat, $lon));
    }

    public static function invalidCoordinates(): array
    {
        return [
            'lat too high' => [90.5, 0.0],
            'lat too low' => [-90.5, 0.0],
            'lon too high' => [0.0, 180.5],
            'lon too low' => [0.0, -180.5],
            'lat non-numeric' => ['abc', 0.0],
            'lon non-numeric' => [0.0, 'xyz'],
            'null lat' => [null, 0.0],
            'null lon' => [0.0, null],
        ];
    }
}
