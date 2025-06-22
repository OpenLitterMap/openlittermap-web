<?php
declare(strict_types=1);

namespace Tests\Unit\Achievements;

use App\Enums\Dimension;
use App\Services\Achievements\Tags\TagKeyCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class TagKeyCacheZeroGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Start from a completely cold state
        TagKeyCache::forgetAll();
        Cache::flush();
        Redis::flushall();

        // *** Intentionally DO NOT pre-insert 'alcohol' ***
        // We want getOrCreateId() to exercise the full upsert path.
    }

    /** @test */
    public function get_or_create_id_never_returns_zero_and_is_consistent(): void
    {
        // 1️⃣  First call should insert or pick the row and return a positive id
        $first = TagKeyCache::getOrCreateId('category', 'alcohol');
        $this->assertGreaterThan(0, $first, 'ID must be > 0 on first call');

        // 2️⃣  It should now be cached (RAM + Redis) – second call pulls the same id
        $second = TagKeyCache::getOrCreateId('category', 'alcohol');
        $this->assertSame($first, $second, 'Second call must return the identical id');

        // 3️⃣  The row must exist in the DB with that id
        $dbId = (int) DB::table(Dimension::CATEGORY->table())
            ->where('key', 'alcohol')
            ->value('id');

        $this->assertSame($first, $dbId, 'Database row id must match the cached id');

        // 4️⃣  Redis forward + reverse hashes must be populated with the same positive id
        $redisForward = Redis::hGet("ach:v1:fwd:category", 'alcohol');
        $redisReverse = Redis::hGet("ach:v1:rev:category", (string)$first);

        $this->assertSame((string)$first, $redisForward, 'Redis forward map contains the id');
        $this->assertSame('alcohol',        $redisReverse, 'Redis reverse map contains the key');
    }
}
