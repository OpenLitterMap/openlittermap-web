<?php

namespace App\Services\Achievements\Checkers;

class TypesChecker extends OptimizedTagBasedChecker
{
    protected function getCountsKey(): string { return 'types'; }
    protected function getDimensionType(): string { return 'types'; }
    protected function getTagType(): string { return 'type'; }
    protected function getTableName(): string { return 'litter_object_types'; }
}
