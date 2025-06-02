<?php

namespace Tests\Unit\Redis;

use App\Models\Photo;
use App\Services\Redis\RedisMetricsCollector;
use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * RedisMetricsCollector - focused unit tests including change tracking
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
        $photo = $this->makePhoto(['id' => 1, 'user_id' => 1]);

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

        $photo = $this->makePhoto(['id' => 1, 'user_id' => 3, 'summary' => $payload, 'xp' => 10]);

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
            $this->makePhoto([
                'id' => 1,
                'user_id' => $userId,
                'summary' => [
                    'tags' => ['food' => ['cup' => ['quantity' => 1]]],
                ]]
            ),
            $this->makePhoto([
                'id' => 2,
                'user_id' => $userId,
                'summary' => [
                    'tags' => ['food' => ['cup' => ['quantity' => 2]]],
                ]]
            ),
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

        $photo = $this->makePhoto(['id' => 1, 'user_id' => $user]);
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

        $photo = $this->makePhoto(['id' => 1, 'user_id' => $user]);
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
            'id'         => 1,
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
    /*  NEW: queueBatchWithTracking() tests                                  */
    /* ===================================================================== */

    public function test_queueBatchWithTracking_empty_batch(): void
    {
        $result = RedisMetricsCollector::queueBatchWithTracking(8, collect());

        $this->assertSame([], $result['changed_dimensions']);
        $this->assertSame([], $result['previous_counts']);
        $this->assertSame([], $result['new_counts']);
    }

    public function test_queueBatchWithTracking_detects_upload_change(): void
    {
        $userId = 9;
        $photos = collect([
            $this->makePhoto(['id' => 1, 'user_id' => $userId])
        ]);

        $result = RedisMetricsCollector::queueBatchWithTracking($userId, $photos);

        $this->assertContains('uploads', $result['changed_dimensions']);
        $this->assertSame(0, $result['previous_counts']['uploads']);
        $this->assertSame(1, $result['new_counts']['uploads']);
    }

    public function test_queueBatchWithTracking_detects_category_change(): void
    {
        $userId = 10;
        $photos = collect([
            $this->makePhoto([
                'id' => 1,
                'user_id' => $userId,
                'summary' => [
                    'tags' => ['food' => ['cup' => ['quantity' => 1]]]
                ]
            ])
        ]);

        $result = RedisMetricsCollector::queueBatchWithTracking($userId, $photos);

        $this->assertContains('categories', $result['changed_dimensions']);
        $this->assertContains('objects', $result['changed_dimensions']);
        $this->assertArrayNotHasKey('food', $result['previous_counts']['categories']);
        $this->assertSame('1', $result['new_counts']['categories']['food']);
    }

    public function test_queueBatchWithTracking_detects_material_change(): void
    {
        $userId = 11;
        $photos = collect([
            $this->makePhoto([
                'id' => 1,
                'user_id' => $userId,
                'summary' => [
                    'tags' => [
                        'food' => [
                            'cup' => [
                                'quantity' => 1,
                                'materials' => ['plastic' => 1]
                            ]
                        ]
                    ]
                ]
            ])
        ]);

        $result = RedisMetricsCollector::queueBatchWithTracking($userId, $photos);

        $this->assertContains('materials', $result['changed_dimensions']);
        $this->assertArrayNotHasKey('plastic', $result['previous_counts']['materials']);
        $this->assertSame('1', $result['new_counts']['materials']['plastic']);
    }

    public function test_queueBatchWithTracking_detects_brand_change(): void
    {
        $userId = 12;
        $photos = collect([
            $this->makePhoto([
                'id' => 1,
                'user_id' => $userId,
                'summary' => [
                    'tags' => [
                        'food' => [
                            'cup' => [
                                'quantity' => 1,
                                'brands' => ['starbucks' => 1]
                            ]
                        ]
                    ]
                ]
            ])
        ]);

        $result = RedisMetricsCollector::queueBatchWithTracking($userId, $photos);

        $this->assertContains('brands', $result['changed_dimensions']);
        $this->assertArrayNotHasKey('starbucks', $result['previous_counts']['brands']);
        $this->assertSame('1', $result['new_counts']['brands']['starbucks']);
    }

    public function test_queueBatchWithTracking_detects_custom_tag_change(): void
    {
        $userId = 13;
        $photos = collect([
            $this->makePhoto([
                'id' => 1,
                'user_id' => $userId,
                'summary' => [
                    'tags' => [
                        'food' => [
                            'cup' => [
                                'quantity' => 1,
                                'custom_tags' => ['biodegradable' => 1]
                            ]
                        ]
                    ]
                ]
            ])
        ]);

        $result = RedisMetricsCollector::queueBatchWithTracking($userId, $photos);

        $this->assertContains('custom_tags', $result['changed_dimensions']);
        $this->assertArrayNotHasKey('biodegradable', $result['previous_counts']['custom_tags']);
        $this->assertSame('1', $result['new_counts']['custom_tags']['biodegradable']);
    }

    public function test_queueBatchWithTracking_detects_multiple_dimensions(): void
    {
        $userId = 14;

        // Set up some existing data
        Redis::hSet("{u:$userId}:stats", 'uploads', 5);
        Redis::hSet("{u:$userId}:t", 'cup', 2);
        Redis::hSet("{u:$userId}:c", 'food', 2);

        $photos = collect([
            $this->makePhoto([
                'id' => 1,
                'user_id' => $userId,
                'summary' => [
                    'tags' => [
                        'food' => [
                            'cup' => ['quantity' => 1]
                        ],
                        'smoking' => [
                            'butt' => [
                                'quantity' => 2,
                                'materials' => ['paper' => 2]
                            ]
                        ]
                    ]
                ]
            ])
        ]);

        $result = RedisMetricsCollector::queueBatchWithTracking($userId, $photos);

        // Should detect all changed dimensions
        $this->assertContains('uploads', $result['changed_dimensions']);
        $this->assertContains('categories', $result['changed_dimensions']); // new category 'smoking'
        $this->assertContains('objects', $result['changed_dimensions']);    // new object 'butt'
        $this->assertContains('materials', $result['changed_dimensions']);  // new material 'paper'

        // Verify counts
        $this->assertSame(5, $result['previous_counts']['uploads']);
        $this->assertSame(6, $result['new_counts']['uploads']);
        $this->assertSame('3', $result['new_counts']['objects']['cup']); // 2 + 1
        $this->assertSame('2', $result['new_counts']['objects']['butt']);
    }

    public function test_queueBatchWithTracking_no_changes_when_values_unchanged(): void
    {
        $userId = 15;

        // Process a photo with objects already counted
        Redis::hSet("{u:$userId}:stats", 'uploads', 10);
        Redis::hSet("{u:$userId}:stats", 'streak', 1);
        Redis::hSet("{u:$userId}:t", 'cup', 5);

        // Mark today as already having uploads to prevent streak change
        $today = now()->format('Y-m-d');
        Redis::setex("{u:$userId}:up:{$today}", 86400, '1');

        // Photo with no tags (empty summary)
        $photos = collect([
            $this->makePhoto([
                'id' => 1,
                'user_id' => $userId,
                'summary' => []  // Empty summary
            ])
        ]);

        $result = RedisMetricsCollector::queueBatchWithTracking($userId, $photos);

        // Only uploads should change (streak won't change because today already has uploads)
        $this->assertSame(['uploads'], $result['changed_dimensions']);
        $this->assertNotContains('objects', $result['changed_dimensions']);
        $this->assertNotContains('categories', $result['changed_dimensions']);
        $this->assertNotContains('streak', $result['changed_dimensions']);
    }

    public function test_queueBatchWithTracking_handles_streak_changes(): void
    {
        $userId = 16;

        // Set up yesterday's upload
        $yesterday = now()->subDay()->format('Y-m-d');
        Redis::setex("{u:$userId}:up:$yesterday", 86400, 1);
        Redis::hSet("{u:$userId}:stats", 'streak', 3);

        $photos = collect([
            $this->makePhoto(['id' => 1, 'user_id' => $userId])
        ]);

        $result = RedisMetricsCollector::queueBatchWithTracking($userId, $photos);

        $this->assertContains('streak', $result['changed_dimensions']);
        $this->assertSame(3, $result['previous_counts']['streak']);
        $this->assertSame(4, $result['new_counts']['streak']); // Incremented
    }

    public function test_queueBatchWithTracking_processes_multiple_photos(): void
    {
        $userId = 17;

        $photos = collect([
            $this->makePhoto([
                'id' => 1,
                'user_id' => $userId,
                'summary' => [
                    'tags' => ['food' => ['cup' => ['quantity' => 2]]]
                ]
            ]),
            $this->makePhoto([
                'id' => 2,
                'user_id' => $userId,
                'summary' => [
                    'tags' => ['food' => ['cup' => ['quantity' => 3]]]
                ]
            ]),
            $this->makePhoto([
                'id' => 3,
                'user_id' => $userId,
                'summary' => [
                    'tags' => ['smoking' => ['butt' => ['quantity' => 1]]]
                ]
            ])
        ]);

        $result = RedisMetricsCollector::queueBatchWithTracking($userId, $photos);

        // Should accumulate all changes
        $this->assertSame(3, $result['new_counts']['uploads']);
        $this->assertSame('5', $result['new_counts']['objects']['cup']); // 2 + 3
        $this->assertSame('1', $result['new_counts']['objects']['butt']);
        $this->assertContains('categories', $result['changed_dimensions']);
        $this->assertContains('objects', $result['changed_dimensions']);
    }

    public function test_queueBatchWithTracking_ignores_already_processed(): void
    {
        $userId = 18;

        // Mark photo 1 as already processed
        Redis::sAdd('p:done', 1);

        $photos = collect([
            $this->makePhoto([
                'id' => 1,
                'user_id' => $userId,
                'summary' => [
                    'tags' => ['food' => ['cup' => ['quantity' => 10]]]
                ]
            ]),
            $this->makePhoto([
                'id' => 2,
                'user_id' => $userId,
                'summary' => [
                    'tags' => ['food' => ['cup' => ['quantity' => 1]]]
                ]
            ])
        ]);

        $result = RedisMetricsCollector::queueBatchWithTracking($userId, $photos);

        // Should only count photo 2
        $this->assertSame(1, $result['new_counts']['uploads']);
        $this->assertSame('1', $result['new_counts']['objects']['cup']); // Only 1, not 11
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
