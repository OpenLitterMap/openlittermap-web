<?php

namespace App\Services\Achievements\Checkers;

class MaterialsChecker extends OptimizedTagBasedChecker
{
    protected function getCountsKey(): string { return 'materials'; }
    protected function getDimensionType(): string { return 'materials'; }
    protected function getTagType(): string { return 'material'; }
    protected function getTableName(): string { return 'materials'; }
}
