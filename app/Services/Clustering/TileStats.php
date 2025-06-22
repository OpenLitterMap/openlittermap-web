<?php

namespace App\Services\Clustering;

final class TileStats
{
    public function __construct(
        public readonly int $photoCount,
        public readonly int $clusterCount
    ) {}
}
