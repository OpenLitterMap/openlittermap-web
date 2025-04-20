<?php

namespace Tests\Unit\Redis;

use App\Models\Photo;
use App\Services\Redis\UpdateRedisService;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class UpdateRedisServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Redis::flushdb();
    }

    /** @test */
    public function it_increments_photos_and_zeroes_for_empty_summary()
    {
        $photo = new Photo();
        $photo->forceFill(['summary' => []]);

        // Ensure created_at is set so timeseries won't blow up
        $photo->created_at = Carbon::parse('2025-04-20 10:00:00');

        // Stub relations
        $photo->setRelation('country', (object)['id' => 1]);
        $photo->setRelation('state',   (object)['id' => 2]);
        $photo->setRelation('city',    (object)['id' => 3]);
        $photo->setRelation('user',    (object)['id' => 4]);

        (new UpdateRedisService())->updateRedis($photo);

        // Global totals
        $this->assertSame('1', Redis::hget('global:totals', 'photos'));
        $this->assertSame('0', Redis::hget('global:totals', 'tags'));
        $this->assertSame('0', Redis::hget('global:totals', 'custom_tags'));

        // No breakdown hashes
        $this->assertEmpty(Redis::hgetall('global:totals:categories'));
        $this->assertEmpty(Redis::hgetall('global:totals:objects'));

        // Time-series keys for the date 2025-04-20
        $this->assertSame('1', Redis::get('global:ts:daily:photos:2025-04-20'));
        $this->assertSame('1', Redis::get('global:ts:weekly:photos:2025-16'));
        $this->assertSame('1', Redis::get('global:ts:monthly:photos:2025-04'));
        $this->assertSame('1', Redis::get('global:ts:yearly:photos:2025'));

        // Country scope mirrors photos count and timeseries
        $this->assertSame('1', Redis::hget('country:1:totals', 'photos'));
        $this->assertSame('1', Redis::get('country:1:ts:daily:photos:2025-04-20'));
    }

    /** @test */
    public function it_populates_all_breakdowns_based_on_summary()
    {
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
                        'materials'   => [],
                        'brands'      => [],
                        'custom_tags' => [],
                    ],
                ],
            ],
            'totals' => [
                'total_tags'    => 8,
                'total_objects' => 5,
                'by_category'   => ['smoking' => 4, 'food' => 3],
                'materials'     => 1,
                'brands'        => 1,
                'custom_tags'   => 1,
            ],
        ];

        $photo = new Photo();
        $photo->forceFill(['summary' => $payload]);
        $photo->created_at = Carbon::parse('2025-04-20 15:30:00');

        $photo->setRelation('country', (object)['id' => 11]);
        $photo->setRelation('state',   (object)['id' => 22]);
        $photo->setRelation('city',    (object)['id' => 33]);
        $photo->setRelation('user',    (object)['id' => 44]);

        (new UpdateRedisService())->updateRedis($photo);

        // Global totals
        $this->assertSame('1', Redis::hget('global:totals', 'photos'));
        $this->assertSame('8', Redis::hget('global:totals', 'tags'));
        $this->assertSame('1', Redis::hget('global:totals', 'custom_tags'));

        // Category breakdowns
        $this->assertSame('4', Redis::hget('global:totals:categories', 'smoking'));
        $this->assertSame('3', Redis::hget('global:totals:categories', 'food'));

        // Object breakdowns
        $this->assertSame('2', Redis::hget('global:totals:objects', 'butts'));
        $this->assertSame('3', Redis::hget('global:totals:objects', 'cups'));

        // Material & brand breakdown
        $this->assertSame('1', Redis::hget('global:totals:materials', 'paper'));
        $this->assertSame('1', Redis::hget('global:totals:brands', 'brandX'));

        // Custom-tags breakdown
        $this->assertSame(
            '1',
            Redis::hget('global:totals:custom_tags_breakdown', 'cleanup')
        );

        // Time-series for this date
        $this->assertSame('1', Redis::get('global:ts:daily:photos:2025-04-20'));

        // Country:11 checks
        $this->assertSame('1', Redis::hget('country:11:totals', 'photos'));
        $this->assertSame(
            '4',
            Redis::hget('country:11:totals:categories', 'smoking')
        );
        $this->assertSame('1', Redis::get('country:11:ts:daily:photos:2025-04-20'));
    }

    /** @test */
    public function it_handles_bulk_photo_summaries_and_aggregates_correctly()
    {
        // fixed timestamp so all time‑series keys match
        $ts = Carbon::parse('2025-04-20 12:00:00');

        // accumulators for expected values
        $expectedPhotos      = 0;
        $expectedTags        = 0;
        $expectedCustomTags  = 0;
        $expectedByCategory  = [];
        $expectedObjects     = [];
        $expectedMaterials   = [];
        $expectedBrands      = [];
        $expectedCustomBreak = [];

        // simulate 100 photos with alternating summaries
        for ($i = 1; $i <= 100; $i++) {
            // odd: smoking→butts + extras, even: food→cups without extras
            if ($i % 2 === 1) {
                $summary = [
                    'tags'   => [
                        'smoking' => [
                            'butts' => [
                                'quantity'    => 1,
                                'materials'   => ['paper'  => 1],
                                'brands'      => ['brandX' => 1],
                                'custom_tags' => ['cleanup'=> 1],
                            ],
                        ],
                    ],
                    'totals' => [
                        'total_tags'    => 4,  // 1 + (1 material + 1 brand + 1 custom)
                        'total_objects' => 1,
                        'by_category'   => ['smoking' => 3], // excludes custom
                        'materials'     => 1,
                        'brands'        => 1,
                        'custom_tags'   => 1,
                    ],
                ];
            } else {
                $summary = [
                    'tags'   => [
                        'food' => [
                            'cups' => [
                                'quantity'    => 2,
                                'materials'   => [],
                                'brands'      => [],
                                'custom_tags' => [],
                            ],
                        ],
                    ],
                    'totals' => [
                        'total_tags'    => 2,
                        'total_objects' => 2,
                        'by_category'   => ['food' => 2],
                        'materials'     => 0,
                        'brands'        => 0,
                        'custom_tags'   => 0,
                    ],
                ];
            }

            // accumulate expectations
            $expectedPhotos++;
            $expectedTags       += $summary['totals']['total_tags'];
            $expectedCustomTags += $summary['totals']['custom_tags'];

            foreach ($summary['totals']['by_category'] as $cat => $qty) {
                $expectedByCategory[$cat] = ($expectedByCategory[$cat] ?? 0) + $qty;
            }
            foreach ($summary['tags'] as $cat => $objects) {
                foreach ($objects as $obj => $data) {
                    $expectedObjects[$obj]      = ($expectedObjects[$obj] ?? 0) + $data['quantity'];
                    foreach ($data['materials'] as $mat => $mqty) {
                        $expectedMaterials[$mat] = ($expectedMaterials[$mat] ?? 0) + $mqty;
                    }
                    foreach ($data['brands'] as $b => $bqty) {
                        $expectedBrands[$b] = ($expectedBrands[$b] ?? 0) + $bqty;
                    }
                    foreach ($data['custom_tags'] as $ct => $ctqty) {
                        $expectedCustomBreak[$ct] = ($expectedCustomBreak[$ct] ?? 0) + $ctqty;
                    }
                }
            }

            // create a photo skeleton and run
            $photo = new Photo();
            $photo->forceFill(['summary' => $summary]);
            $photo->created_at = $ts;
            $photo->setRelation('country', (object)['id' => 1]);
            $photo->setRelation('state',   (object)['id' => 1]);
            $photo->setRelation('city',    (object)['id' => 1]);
            $photo->setRelation('user',    (object)['id' => 1]);

            (new UpdateRedisService())->updateRedis($photo);
        }

        // Assert totals
        $this->assertSame((string)$expectedPhotos, Redis::hget('global:totals', 'photos'));
        $this->assertSame((string)$expectedTags,   Redis::hget('global:totals', 'tags'));
        $this->assertSame((string)$expectedCustomTags, Redis::hget('global:totals', 'custom_tags'));

        // Assert by-category
        foreach ($expectedByCategory as $cat => $qty) {
            $this->assertSame((string)$qty, Redis::hget("global:totals:categories", $cat));
        }

        // Assert object breakdown
        foreach ($expectedObjects as $obj => $qty) {
            $this->assertSame((string)$qty, Redis::hget("global:totals:objects", $obj));
        }

        // Assert material & brand breakdowns
        foreach ($expectedMaterials as $mat => $qty) {
            $this->assertSame((string)$qty, Redis::hget("global:totals:materials", $mat));
        }
        foreach ($expectedBrands as $b => $qty) {
            $this->assertSame((string)$qty, Redis::hget("global:totals:brands", $b));
        }

        // Assert custom‐tag breakdown
        foreach ($expectedCustomBreak as $ct => $qty) {
            $this->assertSame((string)$qty, Redis::hget("global:totals:custom_tags_breakdown", $ct));
        }

        // Assert time-series counter for that date
        $this->assertSame((string)$expectedPhotos, Redis::get('global:ts:daily:photos:' . $ts->format('Y-m-d')));
    }
}
