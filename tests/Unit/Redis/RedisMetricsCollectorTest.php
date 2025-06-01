<?php

namespace Tests\Unit\Redis;

use App\Models\Photo;
use App\Services\Redis\RedisMetricsCollector;
use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * RedisMetricsCollector - focused unit tests
 *
 * NOTE
 * ──────────────────────────────────────────────────────────────
 * • We never hit MySQL: every Photo is an unsaved model instance.
 * • We flush Redis before & after each test to guarantee isolation.
 * • Keys verified here mirror the *current* implementation (May-2025).
 */
class RedisMetricsCollectorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Redis::flushDB();
    }

    protected function tearDown(): void
    {
        Redis::flushDB();
        parent::tearDown();
    }

    /* --------------------------------------------------------------------- */
    /*  queue() – happy path with empty summary                              */
    /* --------------------------------------------------------------------- */
    public function test_queue_handles_empty_summary(): void
    {
        $photo = $this->makePhoto(['user_id' => 1]);

        RedisMetricsCollector::queue($photo);

        $this->assertSame('1', Redis::hGet('{u:1}:stats', 'uploads'));
        $this->assertSame('0', Redis::hGet('{u:1}:stats', 'xp'));
        $this->assertSame('1', Redis::hGet('{u:1}:stats', 'streak'));

        $monthKey = '{g}:' . now()->format('Y-m') . ':t';
        $this->assertSame('1', Redis::hGet($monthKey, 'p'));  // monthly photo counter
    }

    /* --------------------------------------------------------------------- */
    /*  queue() is idempotent per photo id                                   */
    /* --------------------------------------------------------------------- */
    public function test_queue_prevents_double_counting(): void
    {
        $photo = $this->makePhoto(['id' => 123, 'user_id' => 2]);

        RedisMetricsCollector::queue($photo);
        RedisMetricsCollector::queue($photo);   // second call ignored

        $this->assertSame('1', Redis::hGet('{u:2}:stats', 'uploads'));
        $this->assertTrue(Redis::sIsMember('p:done', 123));
    }

    /* --------------------------------------------------------------------- */
    /*  Complex payload → categorisation, materials, brands, globals         */
    /* --------------------------------------------------------------------- */
    public function test_queue_processes_full_payload(): void
    {
        $payload = [
            'tags' => [
                'food' => [
                    'cup' => [
                        'quantity'  => 3,
                        'materials' => ['paper' => 2, 'plastic' => 1],
                        'brands'    => ['starbucks' => 3],
                    ],
                ],
                'smoking' => [
                    'butt' => [
                        'quantity'  => 2,
                        'materials' => ['paper' => 2],
                        'brands'    => ['marlboro' => 2],
                    ],
                ],
            ],
        ];

        $photo = $this->makePhoto(['user_id' => 3, 'summary' => $payload, 'xp' => 10]);

        RedisMetricsCollector::queue($photo);

        /* user hashes ----------------------------------------------------- */
        $this->assertSame('3', Redis::hGet('{u:3}:t', 'cup'));         // objects
        $this->assertSame('1', Redis::hGet('{u:3}:m', 'plastic'));     // materials
        $this->assertSame('3', Redis::hGet('{u:3}:brands', 'starbucks'));

        /* global mirrors -------------------------------------------------- */
        $this->assertSame('3', Redis::hGet('{g}:t', 'cup'));
        $this->assertSame('3', Redis::hGet('{g}:c', 'food'));
        $this->assertSame('2', Redis::hGet('{g}:brands', 'marlboro'));

        /* monthly XP ------------------------------------------------------ */
        $monthKey = '{g}:' . now()->format('Y-m') . ':t';
        $this->assertSame('10', Redis::hGet($monthKey, 'xp'));
    }

    /* --------------------------------------------------------------------- */
    /*  queueBatch() aggregates correctly & writes once                      */
    /* --------------------------------------------------------------------- */
    public function test_queueBatch_accumulates_photos(): void
    {
        $userId = 4;
        $photos = collect([
            $this->makePhoto(['user_id' => $userId, 'summary' => [
                'tags' => ['food' => ['cup' => ['quantity' => 1]]],
            ]]),
            $this->makePhoto(['id' => 2, 'user_id' => $userId, 'summary' => [
                'tags' => ['food' => ['cup' => ['quantity' => 2]]],
            ]]),
        ]);

        RedisMetricsCollector::queueBatch($userId, $photos);

        $this->assertSame('3', Redis::hGet('{u:4}:t', 'cup'));      // 1 + 2
        $this->assertSame('2', Redis::hGet('{u:4}:stats', 'uploads'));
        $this->assertTrue(Redis::sIsMember('p:done', 2));
    }

    /* --------------------------------------------------------------------- */
    /*  Streak logic: consecutive vs gap                                     */
    /* --------------------------------------------------------------------- */
    public function test_streak_increments_with_consecutive_days(): void
    {
        $user = 5;
        // yesterday flag
        $yesterday = now()->subDay()->format('Y-m-d');
        Redis::setex("{u:$user}:up:$yesterday", 86400, 1);
        Redis::hSet("{u:$user}:stats", 'streak', 4);

        $photo = $this->makePhoto(['user_id' => $user]);
        RedisMetricsCollector::queue($photo);

        $this->assertSame('5', Redis::hGet("{u:$user}:stats", 'streak'));
    }

    public function test_streak_resets_after_gap(): void
    {
        $user = 6;
        // two-days-ago flag
        $twoDaysAgo = now()->subDays(2)->format('Y-m-d');
        Redis::setex("{u:$user}:up:$twoDaysAgo", 86400, 1);
        Redis::hSet("{u:$user}:stats", 'streak', 10);

        $photo = $this->makePhoto(['user_id' => $user]);
        RedisMetricsCollector::queue($photo);

        $this->assertSame('1', Redis::hGet("{u:$user}:stats", 'streak'));
    }

    /* --------------------------------------------------------------------- */
    /*  Geo-scoped counters & TTL                                            */
    /* --------------------------------------------------------------------- */
    public function test_geo_scopes_and_ttl(): void
    {
        $date  = now()->format('Y-m-d');
        $photo = $this->makePhoto([
            'user_id'    => 7,
            'country_id' => 1,
            'state_id'   => 2,
            'city_id'    => 3,
        ]);

        RedisMetricsCollector::queue($photo);

        $this->assertSame('1', Redis::hGet('{g}:t:p', $date));
        $this->assertSame('1', Redis::hGet('c:1:t:p', $date));
        $this->assertSame('1', Redis::hGet('s:2:t:p', $date));
        $this->assertSame('1', Redis::hGet('ci:3:t:p', $date));

        // TTL (~2 years) exists
        $ttl = Redis::pTtl('{g}:t:p');
        $this->assertGreaterThan(60 * 60 * 24 * 365, $ttl / 1000);
    }

    /* --------------------------------------------------------------------- */
    /*  getUserCounts returns sane defaults                                  */
    /* --------------------------------------------------------------------- */
    public function test_getUserCounts_defaults(): void
    {
        $counts = RedisMetricsCollector::getUserCounts(999);
        $this->assertSame(0, $counts['uploads']);
        $this->assertSame([], $counts['objects']);
    }

    /* ===================================================================== */
    /*  Helper – in-memory Photo                                             */
    /* ===================================================================== */
    private function makePhoto(array $attrs = []): Photo
    {
        $p = new Photo();

        // sensible defaults
        $p->id         = $attrs['id']         ?? null;
        $p->user_id    = $attrs['user_id']    ?? 1;
        $p->created_at = $attrs['created_at'] ?? Carbon::now();
        $p->xp         = $attrs['xp']         ?? null;
        $p->summary    = $attrs['summary']    ?? [];
        $p->country_id = $attrs['country_id'] ?? null;
        $p->state_id   = $attrs['state_id']   ?? null;
        $p->city_id    = $attrs['city_id']    ?? null;

        // we never persist → no FK constraints
        $p->exists = false;

        return $p;
    }
}
