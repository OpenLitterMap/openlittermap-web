<?php

namespace App\Services\Achievements\Checkers;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

abstract class AchievementChecker
{
    /**
     * Check which achievements should be unlocked
     * @return array Array of achievement IDs that should be unlocked
     */
    abstract public function check(array $counts, Collection $definitions, array $alreadyUnlocked): array;

    /**
     * Helper to get tag ID from cache or database
     */
    protected function getTagId(string $table, string $key): ?int
    {
        return Cache::remember("tag:{$table}:{$key}", 3600, function () use ($table, $key) {
            return DB::table($table)->where('key', $key)->value('id');
        });
    }
}
