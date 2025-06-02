<?php

namespace App\Services\Achievements\Checkers;

use Illuminate\Support\Collection;

class BrandsChecker extends OptimizedTagBasedChecker
{
    protected function getCountsKey(): string { return 'brands'; }
    protected function getDimensionType(): string { return 'brands'; }
    protected function getTagType(): string { return 'brand'; }
    protected function getTableName(): string { return 'brandslist'; }
}
