<?php
namespace App\Services\Achievements\Tags;

use App\Models\Litter\Tags\{BrandList, Category, CustomTagNew, LitterObject, Materials};
use App\Services\Achievements\AchievementEngine;
use Illuminate\Support\Facades\{App, Cache};

final class TagKeyCache
{
    /* ------------------------------------------- */
    /*  cache keys (id → key maps)                 */
    /* ------------------------------------------- */
    private const CK = [
        'object'    => 'ach:map:object',
        'category'  => 'ach:map:category',
        'material'  => 'ach:map:material',
        'brand'     => 'ach:map:brand',
        'customTag' => 'ach:map:customTag',
    ];

    /** in-memory reverse maps (key → id) for this PHP request */
    private static array $reverse = [];

    /* ------------------------------------------- */
    /*  public API                                 */
    /* ------------------------------------------- */

    /** id → key                                 */
    public static function get(string $dim): array
    {
        return Cache::rememberForever(self::CK[$dim], function () use ($dim) {
            return match ($dim) {
                'object'    => LitterObject::pluck('key', 'id')->all(),
                'category'  => Category   ::pluck('key', 'id')->all(),
                'material'  => Materials  ::pluck('key', 'id')->all(),
                'brand'     => BrandList  ::pluck('key', 'id')->all(),
                'customTag' => CustomTagNew::pluck('key', 'id')->all(),
            };
        });
    }

    /** key → id  (returns null if unknown)      */
    public static function idFor(string $dim, string $key): ?int
    {
        /* Build & memoise the reverse map only once per request */
        if (!isset(self::$reverse[$dim])) {
            self::$reverse[$dim] = array_flip(self::get($dim)); // key ⇒ id
        }

        return self::$reverse[$dim][$key] ?? null;
    }

    /* ------------------------------------------- */
    /*  cache invalidation helpers                */
    /* ------------------------------------------- */

    public static function forget(string $dim): void
    {
        Cache::forget(self::CK[$dim]);
        unset(self::$reverse[$dim]);                // drop in-memory copy
        App::forgetInstance(AchievementEngine::class);
    }

    public static function forgetAll(): void
    {
        foreach (array_keys(self::CK) as $dim) {
            Cache::forget(self::CK[$dim]);
        }
        self::$reverse = [];
        App::forgetInstance(AchievementEngine::class);
    }
}
