<?php

namespace App\Repositories;

interface ISSContract
{
    public function getSatellites(): array;

    public function getSatelliteId(int $id = 25544): array;

    public function getSatelliteIdPositions(int $id, array $timestamps = []): array;

    public function getCoordinates(float $lat, float $lon): array;
}
