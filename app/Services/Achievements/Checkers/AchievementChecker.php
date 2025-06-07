<?php

namespace App\Services\Achievements\Checkers;

use App\Services\Achievements\Tags\TagKeyCache;
use Illuminate\Support\Collection;

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
        // Get dimension from table name
        $dimMap = [
            'categories' => 'category',
            'litter_objects' => 'object',
            'materials' => 'material',
            'brandslist' => 'brand',
            'custom_tags_new' => 'customTag'
        ];

        $dimension = $dimMap[$table] ?? null;
        if (!$dimension) {
            return null;
        }

        return TagKeyCache::idFor($dimension, $key);
    }
}
