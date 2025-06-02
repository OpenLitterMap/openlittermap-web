<?php

namespace App\Services\Achievements\Checkers;

use Illuminate\Support\Collection;

class CustomTagChecker extends OptimizedTagBasedChecker
{
    protected function getCountsKey(): string { return 'custom_tags'; }
    protected function getDimensionType(): string { return 'customTags'; }
    protected function getTagType(): string { return 'customTag'; }
    protected function getTableName(): string { return 'custom_tags_new'; }

    protected function shouldSumValues(): bool
    {
        return false; // CustomTags might not have dimension-wide achievements
    }
}

