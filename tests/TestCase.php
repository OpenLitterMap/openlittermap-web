<?php

namespace Tests;

use App\Services\Achievements\Tags\TagKeyCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use RefreshDatabase;

    /**
     * Set up the test environment and flush Redis.
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (! app()->environment('testing')) {
            echo "Warning: Not using testing env. Please run php artisan cache:clear \n";
            return;
        }

        // Flush Redis before each test
        Redis::connection()->flushdb();

        // Flush cache (array driver) to reset rate limiters between tests.
        // Without this, ThrottleRequests state accumulates across tests,
        // causing intermittent 429s on LoginTest and ClusteringApiTest.
        Cache::flush();

        // Clear all tag keys from cache
        TagKeyCache::forgetAll();
    }

    /**
     * Get or create the CLO (category_litter_object) pivot id for a category + object pair.
     */
    protected function getCloId(int $categoryId, int $objectId): int
    {
        $cloId = DB::table('category_litter_object')
            ->where('category_id', $categoryId)
            ->where('litter_object_id', $objectId)
            ->value('id');

        if ($cloId) {
            return $cloId;
        }

        return DB::table('category_litter_object')->insertGetId([
            'category_id' => $categoryId,
            'litter_object_id' => $objectId,
        ]);
    }

    /**
     * Get the CLO id for unclassified.other (for brand-only / custom-tag-only rows).
     */
    protected function getUnclassifiedOtherCloId(): int
    {
        $cloId = DB::table('category_litter_object')
            ->join('categories', 'categories.id', '=', 'category_litter_object.category_id')
            ->join('litter_objects', 'litter_objects.id', '=', 'category_litter_object.litter_object_id')
            ->where('categories.key', 'unclassified')
            ->where('litter_objects.key', 'other')
            ->value('category_litter_object.id');

        if ($cloId) {
            return $cloId;
        }

        $catId = DB::table('categories')->insertGetId(['key' => 'unclassified']);
        $objId = DB::table('litter_objects')->insertGetId(['key' => 'other']);

        return DB::table('category_litter_object')->insertGetId([
            'category_id' => $catId,
            'litter_object_id' => $objId,
        ]);
    }

    /**
     * Tear down the test environment and flush Redis.
     */
    protected function tearDown(): void
    {
        // Flush Redis after each test
        Redis::connection()->flushdb();

        parent::tearDown();
    }
}
