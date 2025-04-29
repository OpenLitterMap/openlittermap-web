<?php

namespace App\Services\Achievements;

class Stats
{
    public function __construct(
        public int     $level,
        public int     $xp,
        public int     $photosTotal,
        public int     $currentStreak,
        public array   $localObjects,
        public array   $cumulativeObjects,
        public array   $summary,
        public string  $tod,
        public int     $dow,
    ) {}
}
