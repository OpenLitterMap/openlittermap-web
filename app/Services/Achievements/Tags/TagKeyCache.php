<?php
namespace App\Services\Achievements\Tags;

use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CustomTagNew;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\Materials;
use App\Services\Achievements\AchievementEngine;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\App;

final class TagKeyCache
{
    /* One cache key per dimension */
    private const CK = [
        'object'    => 'ach:map:object',     // [id => key]
        'category'  => 'ach:map:category',
        'material'  => 'ach:map:material',
        'brand'     => 'ach:map:brand',
        'customTag' => 'ach:map:customTag',
    ];

    /** Get (and cache) the id → key map for one dimension */
    public static function get(string $dim): array
    {
        return Cache::rememberForever(self::CK[$dim], function () use ($dim) {
            return match ($dim) {
                'object'    => LitterObject::pluck('key', 'id')->all(),
                'category'  => Category    ::pluck('key', 'id')->all(),
                'material'  => Materials   ::pluck('key', 'id')->all(),
                'brand'     => BrandList   ::pluck('key', 'id')->all(),
                'customTag' => CustomTagNew::pluck('key', 'id')->all(),
            };
        });
    }

    /** Forget the cache for one dimension **and** reset the engine singleton */
    public static function forget(string $dim): void
    {
        Cache::forget(self::CK[$dim]);
        App::forgetInstance(AchievementEngine::class);
    }

    /** Forget every dimension and reset the engine singleton */
    public static function forgetAll(): void
    {
        foreach (array_keys(self::CK) as $dim) {
            Cache::forget(self::CK[$dim]);
        }
        App::forgetInstance(AchievementEngine::class);
    }
}
