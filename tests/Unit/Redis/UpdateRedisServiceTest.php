<?php

namespace Tests\Unit\Redis;

use App\Models\Photo;
use App\Services\Redis\UpdateRedisService;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\TestCase;

class UpdateRedisServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Redis::flushdb();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_increments_photos_and_zeroes_for_empty_summary()
    {
        // Mock a Photo with no summary data
        $photo = Mockery::mock(Photo::class);
        $photo->summary = [];

        // Stub geographic and user relations
        $photo->country = (object)['id' => 1];
        $photo->state   = (object)['id' => 2];
        $photo->city    = (object)['id' => 3];
        $photo->user    = (object)['id' => 4];

        // Run the Redis updater
        (new UpdateRedisService())->updateRedis($photo);

        // Global scope assertions
        $this->assertEquals('1', Redis::hget('global:totals', 'photos'));
        $this->assertEquals('0', Redis::hget('global:totals', 'tags'));
        $this->assertEquals('0', Redis::hget('global:totals', 'custom_tags'));
        $this->assertEmpty(Redis::hgetall('global:totals:categories'));
        $this->assertEmpty(Redis::hgetall('global:totals:objects'));
        $this->assertEmpty(Redis::hgetall('global:totals:materials'));
        $this->assertEmpty(Redis::hgetall('global:totals:brands'));
        $this->assertEmpty(Redis::hgetall('global:totals:custom_tags_breakdown'));

        // Country scope assertions
        $this->assertEquals('1', Redis::hget('country:1:totals', 'photos'));
        $this->assertEquals('0', Redis::hget('country:1:totals', 'tags'));
        $this->assertEquals('0', Redis::hget('country:1:totals', 'custom_tags'));
    }

    /** @test */
    public function it_populates_all_breakdowns_based_on_summary()
    {
        // Prepare a detailed summary payload
        $summary = [
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
                'total_tags'    => 8,   // 2+3 base +1 material +1 brand +1 custom
                'total_objects' => 5,   // 2 + 3
                'by_category'   => ['smoking' => 4, 'food' => 3],
                'materials'     => 1,
                'brands'        => 1,
                'custom_tags'   => 1,
            ],
        ];

        // Mock a Photo with this summary
        $photo = Mockery::mock(Photo::class);
        $photo->summary = $summary;
        $photo->country = (object)['id' => 11];
        $photo->state   = (object)['id' => 22];
        $photo->city    = (object)['id' => 33];
        $photo->user    = (object)['id' => 44];

        // Execute the updater
        (new UpdateRedisService())->updateRedis($photo);

        // Global totals assertions
        $this->assertEquals('1', Redis::hget('global:totals', 'photos'));
        $this->assertEquals('8', Redis::hget('global:totals', 'tags'));
        $this->assertEquals('1', Redis::hget('global:totals', 'custom_tags'));

        // Category breakdown
        $this->assertEquals('4', Redis::hget('global:totals:categories', 'smoking'));
        $this->assertEquals('3', Redis::hget('global:totals:categories', 'food'));

        // Object breakdown
        $this->assertEquals('2', Redis::hget('global:totals:objects', 'butts'));
        $this->assertEquals('3', Redis::hget('global:totals:objects', 'cups'));

        // Material & brand breakdown
        $this->assertEquals('1', Redis::hget('global:totals:materials', 'paper'));
        $this->assertEquals('1', Redis::hget('global:totals:brands', 'brandX'));

        // Custom tags breakdown
        $this->assertEquals('1', Redis::hget('global:totals:custom_tags_breakdown', 'cleanup'));

        // Country scope mirrors global
        $this->assertEquals('1', Redis::hget('country:11:totals', 'photos'));
        $this->assertEquals('8', Redis::hget('country:11:totals', 'tags'));
        $this->assertEquals('4', Redis::hget('country:11:totals:categories', 'smoking'));
    }
}
