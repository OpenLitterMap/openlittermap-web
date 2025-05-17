<?php
namespace App\Models\Litter\Tags\Traits;

use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CustomTagNew;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\Materials;
use App\Services\Achievements\Tags\TagKeyCache;

trait InvalidatesTagKeyCache
{
    protected static function bootInvalidatesTagKeyCache(): void
    {
        static::saved  (fn ($model) => self::invalidate($model));
        static::deleted(fn ($model) => self::invalidate($model));
    }

    private static function invalidate($model): void
    {
        $dim = match (get_class($model)) {
            LitterObject::class => 'object',
            Category    ::class => 'category',
            Materials   ::class => 'material',
            BrandList   ::class => 'brand',
            CustomTagNew::class => 'customTag',
            default => null,
        };

        if ($dim) {
            TagKeyCache::forget($dim);
        }
    }
}
