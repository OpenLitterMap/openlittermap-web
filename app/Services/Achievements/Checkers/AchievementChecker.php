<?php

namespace App\Services\Achievements\Checkers;

use App\Enums\Dimension;
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
            'categories' => Dimension::CATEGORY->value,
            'litter_objects' => Dimension::LITTER_OBJECT->value,
            'materials' => Dimension::MATERIAL->value,
            'brandslist' => Dimension::BRAND->value,
            'custom_tags_new' => Dimension::CUSTOM_TAG->value,
        ];

        $dimension = $dimMap[$table] ?? null;
        if (!$dimension) {
            return null;
        }

        return TagKeyCache::idFor($dimension, $key);
    }
}
