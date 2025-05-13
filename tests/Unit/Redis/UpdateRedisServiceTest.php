<?php

namespace Tests\Unit\Redis;

use App\Models\Photo;
use App\Services\Redis\UpdateRedisService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class UpdateRedisServiceTest extends TestCase
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

    /** @test */
    public function it_increments_photos_and_zeroes_for_empty_summary(): void
    {
        $ts    = Carbon::parse('2025-04-20 10:00:00');
        $month = '{g:2025-04}:t';

        $photo = new Photo();
        $photo->user_id = 4;
        $photo->created_at = $ts;
        $photo->forceFill(['summary' => []]);
        $photo->setRelation('country', (object)['id' => 1]);
        $photo->setRelation('state',   (object)['id' => 2]);
        $photo->setRelation('city',    (object)['id' => 3]);

        app(UpdateRedisService::class)->updateRedis($photo);

        $this->assertSame('1', Redis::hGet($month, 'p'));

        $statsKey = sprintf('{u:%d}:stats', 4);
        $this->assertSame('1', Redis::hGet($statsKey, 'uploads'));

        $this->assertEmpty(Redis::hGetAll('{g}:c'));
        $this->assertEmpty(Redis::hGetAll('{g}:t'));
    }

    /** @test */
    public function it_does_not_double_count_the_same_photo(): void
    {
        $ts    = Carbon::parse('2025-05-01 08:00:00');
        $month = '{g:2025-05}:t';

        $photo = new Photo();
        $photo->id = 4242;
        $photo->user_id = 9;
        $photo->created_at = $ts;
        $photo->forceFill(['summary' => []]);
        $photo->setRelation('country', (object)['id' => 77]);
        $photo->setRelation('country', (object)['id' => 77]);
        $photo->setRelation('state',   (object)['id' => 88]);
        $photo->setRelation('city',    (object)['id' => 99]);

        // push twice
        app(UpdateRedisService::class)->updateRedis($photo);
        app(UpdateRedisService::class)->updateRedis($photo);

        // still only one counted
        $this->assertSame('1', Redis::hGet($month, 'p'));

        $statsKey = sprintf('{u:%d}:stats', 9);
        $this->assertSame('1', Redis::hGet($statsKey, 'uploads'));
    }

    /** @test */
    public function it_populates_all_breakdowns_based_on_summary(): void
    {
        $ts    = Carbon::parse('2025-04-20 15:30:00');
        $month = '{g:2025-04}:t';

        $payload = [
            'tags' => [
                'smoking' => [
                    'butts' => [
                        'quantity'    => 2,
                        'materials'   => ['paper'  => 1],
                        'brands'      => ['brandX' => 1],
                        'custom_tags' => ['cleanup'=> 1],
                    ],
                ],
                'food' => [
                    'cups' => [
                        'quantity'    => 3,
                        'materials'   => ['glass' => 2],
                        'brands'      => ['brandY' => 3],
                        'custom_tags' => ['scattered'=> 2],
                    ],
                ],
            ],
            'totals' => [
                'by_category' => ['smoking' => 2, 'food' => 3], // new semantics
            ],
        ];

        $photo = new Photo();
        $photo->user_id    = 4;
        $photo->created_at = $ts;
        $photo->forceFill(['summary' => $payload]);
        $photo->setRelation('country', (object)['id' => 11]);
        $photo->setRelation('state',   (object)['id' => 22]);
        $photo->setRelation('city',    (object)['id' => 33]);

        app(UpdateRedisService::class)->updateRedis($photo);

        $this->assertSame('1', Redis::hGet($month, 'p'));
        $this->assertSame('2', Redis::hGet('{g}:c', 'smoking'));
        $this->assertSame('3', Redis::hGet('{g}:c', 'food'));
        $this->assertSame('2', Redis::hGet('{g}:t', 'butts'));
        $this->assertSame('3', Redis::hGet('{g}:t', 'cups'));

        // user breakdowns
        $this->assertSame('2', Redis::hGet('{u:4}:c', 'smoking'));
        $this->assertSame('3', Redis::hGet('{u:4}:c', 'food'));
        $this->assertSame('2', Redis::hGet('{u:4}:t', 'butts'));
        $this->assertSame('3', Redis::hGet('{u:4}:t', 'cups'));
        $this->assertSame('1', Redis::hGet('{u:4}:b', 'm:paper'));
        $this->assertSame('2', Redis::hGet('{u:4}:b', 'm:glass'));
        $this->assertSame('1', Redis::hGet('{u:4}:b', 'b:brandX'));
        $this->assertSame('3', Redis::hGet('{u:4}:b', 'b:brandY'));
        $this->assertSame('1', Redis::hGet('{u:4}:b', 'c:cleanup'));
        $this->assertSame('2', Redis::hGet('{u:4}:b', 'c:scattered'));
    }

    /** @test */
    public function it_updates_xp_and_sets_ttls_on_time_series(): void
    {
        $ts    = Carbon::parse('2025-04-21 06:30:00');
        $month = '{g:2025-04}:t';

        $photo = new Photo();
        $photo->user_id    = 5;
        $photo->xp         = 12;
        $photo->created_at = $ts;
        $photo->forceFill(['summary' => []]);
        $photo->setRelation('country', (object)['id' => 99]);
        $photo->setRelation('state',   (object)['id' => 99]);
        $photo->setRelation('city',    (object)['id' => 99]);

        app(UpdateRedisService::class)->updateRedis($photo);

        $this->assertSame('12',
            Redis::hGet(sprintf('{u:%d}:stats', 5), 'xp')
        );

        $ttl = Redis::pTTL($month);
        $this->assertTrue($ttl === -1 || $ttl > 0, "TTL should be -1 (no expire) or positive, got $ttl");
    }

    /** @test */
    public function it_handles_bulk_photo_summaries_and_aggregates_correctly(): void
    {
        $ts    = Carbon::parse('2025-04-20 12:00:00');
        $month = '{g:2025-04}:t';

        $expectedPhotos = 0;
        $expCats = $expObjs = [];
        $expMat  = $expBrand = $expCust = [];

        for ($i = 1; $i <= 100; $i++) {
            $summary = ($i & 1)
                ? [
                    'tags' => [
                        'smoking' => [
                            'butts' => [
                                'quantity' => 1,
                                'materials' => ['paper'=>1],
                                'brands'    => ['brandX'=>1],
                                'custom_tags'=>['cleanup'=>1],
                            ],
                        ],
                    ],
                    'totals' => ['by_category' => ['smoking' => 1]],
                ]
                : [
                    'tags' => [
                        'food' => [
                            'cups' => [
                                'quantity' => 2,
                                'materials' => [],
                                'brands'    => [],
                                'custom_tags'=>[],
                            ],
                        ],
                    ],
                    'totals' => ['by_category' => ['food' => 2]],
                ];

            $expectedPhotos++;

            foreach ($summary['totals']['by_category'] as $c => $q)
                $expCats[$c] = ($expCats[$c] ?? 0) + $q;

            foreach ($summary['tags'] as $objs)
                foreach ($objs as $o => $d) {
                    $expObjs[$o] = ($expObjs[$o] ?? 0) + $d['quantity'];
                    foreach ($d['materials'] as $m => $q)
                        $expMat[$m] = ($expMat[$m] ?? 0) + $q;
                    foreach ($d['brands'] as $b => $q)
                        $expBrand[$b] = ($expBrand[$b] ?? 0) + $q;
                    foreach ($d['custom_tags'] as $ct => $q)
                        $expCust[$ct] = ($expCust[$ct] ?? 0) + $q;
                }

            $p = new Photo();
            $p->user_id    = 1;
            $p->created_at = $ts;
            $p->forceFill(['summary' => $summary]);
            $p->setRelation('country', (object)['id' => 1]);
            $p->setRelation('state',   (object)['id' => 1]);
            $p->setRelation('city',    (object)['id' => 1]);

            app(UpdateRedisService::class)->updateRedis($p);
        }

        $this->assertSame((string)$expectedPhotos, Redis::hGet($month, 'p'));
        foreach ($expCats  as $c => $q) $this->assertSame((string)$q, Redis::hGet('{g}:c', $c));
        foreach ($expObjs  as $o => $q) $this->assertSame((string)$q, Redis::hGet('{g}:t', $o));
        foreach ($expObjs  as $o => $q) $this->assertSame((string)$q, Redis::hGet('{u:1}:t', $o));
        foreach ($expMat   as $m => $q) $this->assertSame((string)$q, Redis::hGet('{u:1}:b', "m:$m"));
        foreach ($expBrand as $b => $q) $this->assertSame((string)$q, Redis::hGet('{u:1}:b', "b:$b"));
        foreach ($expCust  as $ct=> $q) $this->assertSame((string)$q, Redis::hGet('{u:1}:b', "c:$ct"));
    }
}
