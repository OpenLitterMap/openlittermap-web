<?php

namespace App\Services\Achievements;

final class Stats
{
    public function __construct(
        public int    $userId,
        public int    $level,
        public int    $xp,
        public int    $photosTotal,
        public int    $currentStreak,
        public array  $localObjects,      // current photo
        public array  $cumulativeObjects, // all‑time
        public array  $summary,
        public string $tod,               // morning / afternoon / …
        public int    $dow,               // 0–6
    ) {}
}
