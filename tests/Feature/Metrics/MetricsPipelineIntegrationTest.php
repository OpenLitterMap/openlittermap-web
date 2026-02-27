<?php

namespace Tests\Feature\Metrics;

use App\Enums\LocationType;
use App\Models\Location\Country;
use App\Models\Photo;
use App\Models\Users\User;
use App\Services\Metrics\MetricsService;
use App\Services\Redis\RedisKeys;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class MetricsPipelineIntegrationTest extends TestCase
{
    private MetricsService $metricsService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->metricsService = app(MetricsService::class);
    }

    /**
     * Helper to build a v5.0 nested summary with given objects.
     * Category key 1, object keys as provided.
     */
    private function buildSummary(array $objects, int $xp): array
    {
        $totalObjects = 0;
        $objectsData = [];

        foreach ($objects as $objectId => $quantity) {
            $objectsData[$objectId] = [
                'quantity' => $quantity,
                'materials' => [],
                'brands' => [],
                'custom_tags' => [],
            ];
            $totalObjects += $quantity;
        }

        return [
            'tags' => [1 => $objectsData],
            'totals' => [
                'total_tags' => $totalObjects,
                'total_objects' => $totalObjects,
                'by_category' => [1 => $totalObjects],
                'materials' => 0,
                'brands' => 0,
                'custom_tags' => 0,
            ],
        ];
    }

    /**
     * Get the all-time global aggregate metrics row (user_id=0).
     */
    private function getGlobalAggregate(): ?object
    {
        return DB::table('metrics')
            ->where('timescale', 0)
            ->where('location_type', LocationType::Global->value)
            ->where('location_id', 0)
            ->where('user_id', 0)
            ->first();
    }

    /**
     * Get the all-time global per-user metrics row.
     */
    private function getGlobalUserRow(int $userId): ?object
    {
        return DB::table('metrics')
            ->where('timescale', 0)
            ->where('location_type', LocationType::Global->value)
            ->where('location_id', 0)
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * Get the all-time country-scoped aggregate row (user_id=0).
     */
    private function getCountryAggregate(int $countryId): ?object
    {
        return DB::table('metrics')
            ->where('timescale', 0)
            ->where('location_type', LocationType::Country->value)
            ->where('location_id', $countryId)
            ->where('user_id', 0)
            ->first();
    }

    public function test_create_writes_mysql_metrics_and_redis(): void
    {
        $user = User::factory()->create();
        $photo = Photo::factory()->create([
            'user_id' => $user->id,
            'xp' => 10,
            'summary' => $this->buildSummary([10 => 3, 11 => 2], 10),
        ]);

        $this->metricsService->processPhoto($photo);
        $photo->refresh();

        // Photo processed state
        $this->assertNotNull($photo->processed_at);
        $this->assertEquals(10, (int) $photo->processed_xp);
        $this->assertNotNull($photo->processed_fp);

        // MySQL: global aggregate
        $global = $this->getGlobalAggregate();
        $this->assertNotNull($global);
        $this->assertEquals(1, (int) $global->uploads);
        $this->assertEquals(5, (int) $global->litter); // 3 + 2
        $this->assertEquals(10, (int) $global->xp);

        // MySQL: per-user row
        $userRow = $this->getGlobalUserRow($user->id);
        $this->assertNotNull($userRow);
        $this->assertEquals(1, (int) $userRow->uploads);
        $this->assertEquals(10, (int) $userRow->xp);

        // MySQL: country-scoped row
        $countryRow = $this->getCountryAggregate($photo->country_id);
        $this->assertNotNull($countryRow);
        $this->assertEquals(1, (int) $countryRow->uploads);
        $this->assertEquals(5, (int) $countryRow->litter);

        // Redis: global stats
        $globalScope = RedisKeys::global();
        $this->assertEquals(1, (int) Redis::hGet(RedisKeys::stats($globalScope), 'photos'));
        $this->assertEquals(5, (int) Redis::hGet(RedisKeys::stats($globalScope), 'litter'));
        $this->assertEquals(10, (int) Redis::hGet(RedisKeys::stats($globalScope), 'xp'));

        // Redis: XP leaderboard
        $this->assertEquals(10.0, Redis::zScore(RedisKeys::xpRanking($globalScope), (string) $user->id));

        // Redis: user stats
        $userScope = RedisKeys::user($user->id);
        $this->assertEquals(1, (int) Redis::hGet(RedisKeys::stats($userScope), 'uploads'));
        $this->assertEquals(10, (int) Redis::hGet(RedisKeys::stats($userScope), 'xp'));
        $this->assertEquals(5, (int) Redis::hGet(RedisKeys::stats($userScope), 'litter'));
    }

    public function test_update_calculates_correct_deltas(): void
    {
        $user = User::factory()->create();
        $photo = Photo::factory()->create([
            'user_id' => $user->id,
            'xp' => 10,
            'summary' => $this->buildSummary([10 => 3], 10),
        ]);

        // Initial create
        $this->metricsService->processPhoto($photo);
        $photo->refresh();

        $this->assertEquals(3, (int) $this->getGlobalAggregate()->litter);
        $this->assertEquals(10, (int) $this->getGlobalAggregate()->xp);

        // Simulate tag edit: change summary and XP
        $photo->update([
            'xp' => 25,
            'summary' => $this->buildSummary([10 => 5, 12 => 3], 25),
        ]);
        $photo->refresh();

        // Process again — should compute delta
        $this->metricsService->processPhoto($photo);
        $photo->refresh();

        // Fingerprint should change
        $this->assertEquals(25, (int) $photo->processed_xp);

        // MySQL: litter went from 3 → 8 (delta +5), XP from 10 → 25 (delta +15)
        $global = $this->getGlobalAggregate();
        $this->assertEquals(8, (int) $global->litter);
        $this->assertEquals(25, (int) $global->xp);
        $this->assertEquals(1, (int) $global->uploads); // Uploads unchanged on update

        // Redis: should reflect cumulative values
        $globalScope = RedisKeys::global();
        $this->assertEquals(8, (int) Redis::hGet(RedisKeys::stats($globalScope), 'litter'));
        $this->assertEquals(25, (int) Redis::hGet(RedisKeys::stats($globalScope), 'xp'));
        $this->assertEquals(25.0, Redis::zScore(RedisKeys::xpRanking($globalScope), (string) $user->id));
    }

    public function test_delete_reverses_all_metrics(): void
    {
        $user = User::factory()->create();
        $photo = Photo::factory()->create([
            'user_id' => $user->id,
            'xp' => 15,
            'summary' => $this->buildSummary([10 => 4], 15),
        ]);

        // Create
        $this->metricsService->processPhoto($photo);
        $photo->refresh();

        $globalScope = RedisKeys::global();

        // Verify creation worked
        $this->assertEquals(4, (int) $this->getGlobalAggregate()->litter);
        $this->assertEquals(15, (int) $this->getGlobalAggregate()->xp);
        $this->assertEquals(1, (int) $this->getGlobalAggregate()->uploads);
        $this->assertEquals(15.0, Redis::zScore(RedisKeys::xpRanking($globalScope), (string) $user->id));

        // Delete
        $this->metricsService->deletePhoto($photo);
        $photo->refresh();

        // Photo processing state cleared
        $this->assertNull($photo->processed_at);
        $this->assertNull($photo->processed_xp);
        $this->assertNull($photo->processed_tags);

        // MySQL: all metrics reversed to 0
        $global = $this->getGlobalAggregate();
        $this->assertEquals(0, (int) $global->uploads);
        $this->assertEquals(0, (int) $global->litter);
        $this->assertEquals(0, (int) $global->xp);

        // Redis: stats decremented
        $this->assertEquals(0, (int) Redis::hGet(RedisKeys::stats($globalScope), 'photos'));
        $this->assertEquals(0, (int) Redis::hGet(RedisKeys::stats($globalScope), 'litter'));
        $this->assertEquals(0, (int) Redis::hGet(RedisKeys::stats($globalScope), 'xp'));

        // Redis: user pruned from XP leaderboard (score hit 0)
        $this->assertFalse(Redis::zScore(RedisKeys::xpRanking($globalScope), (string) $user->id));

        // Redis: user stats decremented
        $userScope = RedisKeys::user($user->id);
        $this->assertEquals(0, (int) Redis::hGet(RedisKeys::stats($userScope), 'uploads'));
        $this->assertEquals(0, (int) Redis::hGet(RedisKeys::stats($userScope), 'xp'));
    }

    public function test_full_lifecycle_create_edit_delete(): void
    {
        $user = User::factory()->create();
        $photo = Photo::factory()->create([
            'user_id' => $user->id,
            'xp' => 10,
            'summary' => $this->buildSummary([10 => 2], 10),
        ]);

        $globalScope = RedisKeys::global();
        $countryScope = RedisKeys::country($photo->country_id);

        // Step 1: Create
        $this->metricsService->processPhoto($photo);
        $photo->refresh();

        $this->assertEquals(2, (int) $this->getGlobalAggregate()->litter);
        $this->assertEquals(10, (int) $this->getGlobalAggregate()->xp);
        $this->assertEquals(2, (int) Redis::hGet(RedisKeys::stats($globalScope), 'litter'));
        $this->assertEquals(10, (int) Redis::hGet(RedisKeys::stats($globalScope), 'xp'));
        $this->assertEquals(2, (int) Redis::hGet(RedisKeys::stats($countryScope), 'litter'));

        // Step 2: Edit (increase tags)
        $photo->update([
            'xp' => 20,
            'summary' => $this->buildSummary([10 => 5, 11 => 3], 20),
        ]);
        $photo->refresh();

        $this->metricsService->processPhoto($photo);
        $photo->refresh();

        $this->assertEquals(8, (int) $this->getGlobalAggregate()->litter); // 5 + 3
        $this->assertEquals(20, (int) $this->getGlobalAggregate()->xp);
        $this->assertEquals(1, (int) $this->getGlobalAggregate()->uploads); // Still 1
        $this->assertEquals(8, (int) Redis::hGet(RedisKeys::stats($globalScope), 'litter'));
        $this->assertEquals(20, (int) Redis::hGet(RedisKeys::stats($globalScope), 'xp'));
        $this->assertEquals(20.0, Redis::zScore(RedisKeys::xpRanking($globalScope), (string) $user->id));

        // Step 3: Edit (decrease tags)
        $photo->update([
            'xp' => 5,
            'summary' => $this->buildSummary([10 => 1], 5),
        ]);
        $photo->refresh();

        $this->metricsService->processPhoto($photo);
        $photo->refresh();

        $this->assertEquals(1, (int) $this->getGlobalAggregate()->litter);
        $this->assertEquals(5, (int) $this->getGlobalAggregate()->xp);
        $this->assertEquals(1, (int) Redis::hGet(RedisKeys::stats($globalScope), 'litter'));
        $this->assertEquals(5, (int) Redis::hGet(RedisKeys::stats($globalScope), 'xp'));
        $this->assertEquals(5.0, Redis::zScore(RedisKeys::xpRanking($globalScope), (string) $user->id));

        // Step 4: Delete
        $this->metricsService->deletePhoto($photo);
        $photo->refresh();

        $global = $this->getGlobalAggregate();
        $this->assertEquals(0, (int) $global->uploads);
        $this->assertEquals(0, (int) $global->litter);
        $this->assertEquals(0, (int) $global->xp);

        $this->assertEquals(0, (int) Redis::hGet(RedisKeys::stats($globalScope), 'photos'));
        $this->assertEquals(0, (int) Redis::hGet(RedisKeys::stats($globalScope), 'litter'));
        $this->assertEquals(0, (int) Redis::hGet(RedisKeys::stats($globalScope), 'xp'));
        $this->assertFalse(Redis::zScore(RedisKeys::xpRanking($globalScope), (string) $user->id));

        // Country scope also zeroed
        $this->assertEquals(0, (int) Redis::hGet(RedisKeys::stats($countryScope), 'litter'));
        $this->assertFalse(Redis::zScore(RedisKeys::xpRanking($countryScope), (string) $user->id));
    }

    public function test_location_scoped_metrics_are_independent(): void
    {
        $country1 = Country::factory()->create();
        $country2 = Country::factory()->create();
        $user = User::factory()->create();

        $photo1 = Photo::factory()->create([
            'user_id' => $user->id,
            'country_id' => $country1->id,
            'xp' => 10,
            'summary' => $this->buildSummary([10 => 2], 10),
        ]);

        $photo2 = Photo::factory()->create([
            'user_id' => $user->id,
            'country_id' => $country2->id,
            'xp' => 30,
            'summary' => $this->buildSummary([10 => 6], 30),
        ]);

        $this->metricsService->processPhoto($photo1);
        $this->metricsService->processPhoto($photo2);

        // Global totals: 2 + 6 = 8 litter, 10 + 30 = 40 XP
        $global = $this->getGlobalAggregate();
        $this->assertEquals(8, (int) $global->litter);
        $this->assertEquals(40, (int) $global->xp);
        $this->assertEquals(2, (int) $global->uploads);

        // Country 1: only photo1's data
        $c1 = $this->getCountryAggregate($country1->id);
        $this->assertEquals(2, (int) $c1->litter);
        $this->assertEquals(10, (int) $c1->xp);

        // Country 2: only photo2's data
        $c2 = $this->getCountryAggregate($country2->id);
        $this->assertEquals(6, (int) $c2->litter);
        $this->assertEquals(30, (int) $c2->xp);

        // Redis: country scopes are independent
        $c1Scope = RedisKeys::country($country1->id);
        $c2Scope = RedisKeys::country($country2->id);
        $this->assertEquals(2, (int) Redis::hGet(RedisKeys::stats($c1Scope), 'litter'));
        $this->assertEquals(6, (int) Redis::hGet(RedisKeys::stats($c2Scope), 'litter'));

        // Delete photo1 — only country1 and global should change
        $this->metricsService->deletePhoto($photo1);

        $global = $this->getGlobalAggregate();
        $this->assertEquals(6, (int) $global->litter);
        $this->assertEquals(30, (int) $global->xp);
        $this->assertEquals(1, (int) $global->uploads);

        $c1 = $this->getCountryAggregate($country1->id);
        $this->assertEquals(0, (int) $c1->litter);

        $c2 = $this->getCountryAggregate($country2->id);
        $this->assertEquals(6, (int) $c2->litter);
        $this->assertEquals(30, (int) $c2->xp);
    }

    public function test_soft_delete_preserves_photo_but_removes_from_public_queries(): void
    {
        $user = User::factory()->create();
        $photo = Photo::factory()->create([
            'user_id' => $user->id,
            'is_public' => true,
            'xp' => 10,
            'summary' => $this->buildSummary([10 => 2], 10),
        ]);

        $this->metricsService->processPhoto($photo);

        // Photo exists publicly
        $this->assertEquals(1, Photo::public()->where('id', $photo->id)->count());

        // Soft delete
        $photo->delete();

        // Photo is soft-deleted
        $this->assertSoftDeleted('photos', ['id' => $photo->id]);

        // Photo excluded from public queries
        $this->assertEquals(0, Photo::public()->where('id', $photo->id)->count());

        // Photo still accessible with withTrashed
        $this->assertNotNull(Photo::withTrashed()->find($photo->id));
    }

    public function test_two_users_metrics_are_isolated(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $photo1 = Photo::factory()->create([
            'user_id' => $user1->id,
            'xp' => 10,
            'summary' => $this->buildSummary([10 => 2], 10),
        ]);

        $photo2 = Photo::factory()->create([
            'user_id' => $user2->id,
            'xp' => 20,
            'summary' => $this->buildSummary([10 => 4], 20),
        ]);

        $this->metricsService->processPhoto($photo1);
        $this->metricsService->processPhoto($photo2);

        // Per-user rows are independent
        $u1Row = $this->getGlobalUserRow($user1->id);
        $u2Row = $this->getGlobalUserRow($user2->id);

        $this->assertEquals(10, (int) $u1Row->xp);
        $this->assertEquals(2, (int) $u1Row->litter);

        $this->assertEquals(20, (int) $u2Row->xp);
        $this->assertEquals(4, (int) $u2Row->litter);

        // Redis: user-specific stats isolated
        $this->assertEquals(10, (int) Redis::hGet(RedisKeys::stats(RedisKeys::user($user1->id)), 'xp'));
        $this->assertEquals(20, (int) Redis::hGet(RedisKeys::stats(RedisKeys::user($user2->id)), 'xp'));

        // Redis: both on global leaderboard
        $globalScope = RedisKeys::global();
        $this->assertEquals(10.0, Redis::zScore(RedisKeys::xpRanking($globalScope), (string) $user1->id));
        $this->assertEquals(20.0, Redis::zScore(RedisKeys::xpRanking($globalScope), (string) $user2->id));

        // Delete user1's photo — only user1's metrics should change
        $this->metricsService->deletePhoto($photo1);

        $u1Row = $this->getGlobalUserRow($user1->id);
        $u2Row = $this->getGlobalUserRow($user2->id);

        $this->assertEquals(0, (int) $u1Row->xp);
        $this->assertEquals(20, (int) $u2Row->xp);

        $this->assertFalse(Redis::zScore(RedisKeys::xpRanking($globalScope), (string) $user1->id));
        $this->assertEquals(20.0, Redis::zScore(RedisKeys::xpRanking($globalScope), (string) $user2->id));
    }

    public function test_time_series_rows_created_for_all_timescales(): void
    {
        $user = User::factory()->create();
        $photo = Photo::factory()->create([
            'user_id' => $user->id,
            'xp' => 10,
            'summary' => $this->buildSummary([10 => 2], 10),
        ]);

        $this->metricsService->processPhoto($photo);

        // Should have rows for all 5 timescales (0-4) × global scope × 2 (aggregate + per-user)
        // Plus location scopes (country, state, city depending on factory)
        $totalRows = DB::table('metrics')->count();

        // At minimum: 5 timescales × 1 global scope × 2 = 10 rows
        $this->assertGreaterThanOrEqual(10, $totalRows);

        // Check each timescale exists for global aggregate
        foreach ([0, 1, 2, 3, 4] as $timescale) {
            $row = DB::table('metrics')
                ->where('timescale', $timescale)
                ->where('location_type', LocationType::Global->value)
                ->where('location_id', 0)
                ->where('user_id', 0)
                ->first();

            $this->assertNotNull($row, "Missing global aggregate row for timescale $timescale");
            $this->assertEquals(10, (int) $row->xp);
            $this->assertEquals(2, (int) $row->litter);
        }

        // Check each timescale exists for per-user row
        foreach ([0, 1, 2, 3, 4] as $timescale) {
            $row = DB::table('metrics')
                ->where('timescale', $timescale)
                ->where('location_type', LocationType::Global->value)
                ->where('location_id', 0)
                ->where('user_id', $user->id)
                ->first();

            $this->assertNotNull($row, "Missing per-user row for timescale $timescale");
            $this->assertEquals(10, (int) $row->xp);
        }
    }

    public function test_delete_of_unprocessed_photo_is_noop(): void
    {
        $photo = Photo::factory()->create([
            'processed_at' => null,
            'processed_xp' => null,
            'processed_tags' => null,
        ]);

        // Should not throw
        $this->metricsService->deletePhoto($photo);

        // No metrics rows created
        $this->assertEquals(0, DB::table('metrics')->count());
    }
}
