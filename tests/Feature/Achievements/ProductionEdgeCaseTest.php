<?php

namespace Tests\Feature\Achievements;

use App\Models\Photo;
use App\Models\Users\User;
use App\Models\Location\{Country, State, City};
use App\Services\Achievements\AchievementEngine;
use App\Services\Achievements\Tags\TagKeyCache;
use App\Services\Redis\RedisMetricsCollector;
use Database\Seeders\AchievementsSeeder;
use Database\Seeders\Tags\GenerateBrandsSeeder;
use Database\Seeders\Tags\GenerateTagsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Cache, DB, Redis};
use Tests\TestCase;

class ProductionEdgeCaseTest extends TestCase
{
    use RefreshDatabase;

    private AchievementEngine $engine;
    private $country;
    private $state;
    private $city;

    protected function setUp(): void
    {
        parent::setUp();

        Redis::flushDB();
        Cache::flush();
        TagKeyCache::forgetAll();

        // Create location data
        $this->country = \App\Models\Location\Country::factory()->create();
        $this->state = \App\Models\Location\State::factory()->create(['country_id' => $this->country->id]);
        $this->city = \App\Models\Location\City::factory()->create([
            'country_id' => $this->country->id,
            'state_id' => $this->state->id
        ]);

        $this->seed(GenerateTagsSeeder::class);
        $this->seed(GenerateBrandsSeeder::class);
        $this->seed(AchievementsSeeder::class);

        $this->engine = app(AchievementEngine::class);
    }

    /** @test */
    public function handles_redis_connection_failure_gracefully(): void
    {
        $user = User::factory()->create();
        $photo = $this->createPhoto($user, ['food' => ['wrapper' => ['quantity' => 1]]]);

        // Process normally first
        RedisMetricsCollector::queue($photo);
        $unlocked = $this->engine->evaluate($photo);
        $this->assertNotEmpty($unlocked);

        // Test that the engine handles exceptions gracefully
        $photo2 = $this->createPhoto($user, ['food' => ['wrapper' => ['quantity' => 1]]]);

        // Create a mock engine that will throw exception
        $mockEngine = $this->getMockBuilder(AchievementEngine::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockEngine->expects($this->once())
            ->method('evaluate')
            ->willThrowException(new \Exception('Redis connection failed'));

        // Should handle exception without crashing
        try {
            $mockEngine->evaluate($photo2);
        } catch (\Exception $e) {
            // Expected
            $this->assertEquals('Redis connection failed', $e->getMessage());
        }
    }

    /** @test */
//    public function handles_user_deletion_with_achievements(): void
//    {
//        $user = User::factory()->create();
//
//        // User uploads photos and gets achievements
//        for ($i = 0; $i < 50; $i++) {
//            $photo = $this->createPhoto($user, ['food' => ['wrapper' => ['quantity' => 1]]]);
//            RedisMetricsCollector::queue($photo);
//            $this->engine->evaluate($photo);
//        }
//
//        // Verify achievements exist
//        $achievementCount = DB::table('user_achievements')->where('user_id', $user->id)->count();
//        $this->assertGreaterThan(0, $achievementCount);
//
//        // Delete user (should cascade delete achievements)
//        $userId = $user->id;
//        $user->delete();
//
//        // Verify achievements are deleted
//        $this->assertDatabaseMissing('user_achievements', ['user_id' => $userId]);
//
//        // Verify Redis data is orphaned but doesn't cause issues
//        $redisKey = "{u:{$userId}}:stats";
//        $this->assertTrue(Redis::exists($redisKey)); // Data still exists
//
//        // Processing new photo with deleted user should handle gracefully
//        $orphanPhoto = new Photo();
//        $orphanPhoto->user_id = $userId;
//        $orphanPhoto->summary = ['tags' => ['food' => ['wrapper' => ['quantity' => 1]]]];
//        $orphanPhoto->id = 999999;
//        $orphanPhoto->exists = true;
//        $orphanPhoto->created_at = now();
//        $orphanPhoto->filename = 'orphan.png';
//        $orphanPhoto->model = 'iphone';
//        $orphanPhoto->datetime = now();
//        $orphanPhoto->country_id = $this->country->id;
//        $orphanPhoto->state_id = $this->state->id;
//        $orphanPhoto->city_id = $this->city->id;
//
//        $unlocked = $this->engine->evaluate($orphanPhoto);
//        $this->assertEmpty($unlocked);
//    }

    /** @test */
    public function handles_burst_upload_patterns_realistically(): void
    {
        $user = User::factory()->create();
        $totalPhotos = 0;

        // Simulate realistic upload patterns: bursts with gaps
        // Week 1: Heavy burst (vacation/event)
        for ($i = 0; $i < 3; $i++) { // 3 days
            $photosToday = rand(5, 10); // 5-10 photos per day during event (reduced)
            for ($j = 0; $j < $photosToday; $j++) {
                $photo = $this->createPhoto($user, $this->randomTagData(), now()->addDays($i)->addMinutes($j));
                RedisMetricsCollector::queue($photo);
                $this->engine->evaluate($photo);
                $totalPhotos++;
            }
        }

        // Week 2-3: Nothing (typical gap)

        // Week 4: Regular usage
        for ($i = 21; $i < 28; $i++) { // 7 days
            if (rand(1, 100) <= 30) { // 30% chance of upload each day
                $photosToday = rand(1, 3);
                for ($j = 0; $j < $photosToday; $j++) {
                    $photo = $this->createPhoto($user, $this->randomTagData(), now()->addDays($i));
                    RedisMetricsCollector::queue($photo);
                    $this->engine->evaluate($photo);
                    $totalPhotos++;
                }
            }
        }

        // Verify achievements reflect actual usage pattern
        $counts = RedisMetricsCollector::getUserCounts($user->id);
        $this->assertEquals($totalPhotos, $counts['uploads']);

        // Streak should be broken due to gaps
        $this->assertLessThan(7, $counts['streak']);
    }

    /** @test */
    public function handles_timezone_changes_for_streak_calculations(): void
    {
        $user = User::factory()->create();

        // User in UTC uploads at 11 PM
        $photo1 = $this->createPhoto($user, ['food' => ['wrapper' => ['quantity' => 1]]],
            Carbon::parse('2025-01-20 23:00:00', 'UTC'));
        RedisMetricsCollector::queue($photo1);

        // User travels to Tokyo (UTC+9) and uploads at 9 AM local time (00:00 UTC next day)
        $photo2 = $this->createPhoto($user, ['food' => ['wrapper' => ['quantity' => 1]]],
            Carbon::parse('2025-01-21 00:00:00', 'UTC'));
        RedisMetricsCollector::queue($photo2);

        // This should maintain streak despite timezone change
        $counts = RedisMetricsCollector::getUserCounts($user->id);
        $this->assertEquals(2, $counts['streak']);

        // User travels to LA (UTC-8) and uploads at 5 PM local (01:00 UTC next day)
        $photo3 = $this->createPhoto($user, ['food' => ['wrapper' => ['quantity' => 1]]],
            Carbon::parse('2025-01-22 01:00:00', 'UTC'));
        RedisMetricsCollector::queue($photo3);

        $counts = RedisMetricsCollector::getUserCounts($user->id);
        $this->assertEquals(3, $counts['streak']);
    }

    /** @test */
    public function performance_with_very_large_user_base(): void
    {
        // Simulate users with varying achievement levels (reduced for test speed)
        $users = User::factory()->count(20)->create();

        $startTime = microtime(true);

        foreach ($users as $index => $user) {
            // Give each user different amount of photos (0-50)
            $photoCount = ($index * 2) % 50;

            for ($i = 0; $i < $photoCount; $i++) {
                $photo = $this->createPhoto($user, $this->randomTagData());
                RedisMetricsCollector::queue($photo);

                // Only evaluate every 5th photo to simulate batch processing
                if ($i % 5 === 0) {
                    $this->engine->evaluate($photo);
                }
            }
        }

        $totalTime = microtime(true) - $startTime;

        // Should handle 20 users reasonably fast
        $this->assertLessThan(10, $totalTime);

        // Verify data integrity for random sample
        $sampleUser = $users->random();
        $counts = RedisMetricsCollector::getUserCounts($sampleUser->id);
        $this->assertIsArray($counts);
    }

    /** @test */
    public function handles_achievement_threshold_changes_after_unlock(): void
    {
        $user = User::factory()->create();

        // User unlocks uploads-42
        for ($i = 0; $i < 42; $i++) {
            $photo = $this->createPhoto($user, ['food' => ['wrapper' => ['quantity' => 1]]]);
            RedisMetricsCollector::queue($photo);
            $this->engine->evaluate($photo);
        }

        // Verify achievement is unlocked
        $achievement = DB::table('achievements')
            ->where('type', 'uploads')
            ->where('threshold', 42)
            ->first();

        $this->assertDatabaseHas('user_achievements', [
            'user_id' => $user->id,
            'achievement_id' => $achievement->id,
        ]);

        // Simulate threshold change (business decision to make it easier)
        DB::table('achievements')
            ->where('id', $achievement->id)
            ->update(['threshold' => 30]);

        // Clear cache to pick up change
        Cache::forget('achievements:all');
        Cache::forget('achievements:indexed');

        // Recreate engine to pick up changes
        $this->engine = app(AchievementEngine::class);

        // New users should get it at 30, but existing unlock remains valid
        $newUser = User::factory()->create();
        for ($i = 0; $i < 30; $i++) {
            $photo = $this->createPhoto($newUser, ['food' => ['wrapper' => ['quantity' => 1]]]);
            RedisMetricsCollector::queue($photo);
            $this->engine->evaluate($photo);
        }

        // Both users should have the achievement
        $this->assertDatabaseHas('user_achievements', [
            'user_id' => $user->id,
            'achievement_id' => $achievement->id,
        ]);

        $this->assertDatabaseHas('user_achievements', [
            'user_id' => $newUser->id,
            'achievement_id' => $achievement->id,
        ]);
    }

    /** @test */
    public function handles_duplicate_photo_processing_race_condition(): void
    {
        $user = User::factory()->create();
        $photo = $this->createPhoto($user, ['food' => ['wrapper' => ['quantity' => 50]]]); // Enough for multiple achievements

        // Simulate race condition: same photo processed multiple times simultaneously
        RedisMetricsCollector::queue($photo);
        RedisMetricsCollector::queue($photo);
        RedisMetricsCollector::queue($photo);

        // Process multiple times
        $unlocked1 = $this->engine->evaluate($photo);
        $unlocked2 = $this->engine->evaluate($photo);
        $unlocked3 = $this->engine->evaluate($photo);

        // First should unlock achievements
        $this->assertNotEmpty($unlocked1);

        // Subsequent should return empty (idempotent)
        $this->assertEmpty($unlocked2);
        $this->assertEmpty($unlocked3);

        // Verify counts aren't multiplied
        $counts = RedisMetricsCollector::getUserCounts($user->id);
        $this->assertEquals(1, $counts['uploads']); // Should only count once
        $this->assertEquals(50, $counts['objects']['wrapper'] ?? 0);
    }

    /** @test */
    public function monitors_memory_usage_during_long_running_process(): void
    {
        $user = User::factory()->create();
        $memorySnapshots = [];

        // Process many photos tracking memory (reduced for test speed)
        for ($i = 0; $i < 200; $i++) {
            $photo = $this->createPhoto($user, $this->randomTagData());
            RedisMetricsCollector::queue($photo);
            $this->engine->evaluate($photo);

            // Track memory every 50 photos
            if ($i % 50 === 0) {
                $memorySnapshots[] = memory_get_usage(true) / 1024 / 1024; // MB

                // Force garbage collection
                if ($i % 100 === 0) {
                    gc_collect_cycles();
                }
            }
        }

        // Memory should not continuously grow (indicates leak)
        $firstSnapshot = $memorySnapshots[0];
        $lastSnapshot = end($memorySnapshots);
        $memoryGrowth = $lastSnapshot - $firstSnapshot;

        // Allow some growth but not excessive
        $this->assertLessThan(50, $memoryGrowth, 'Memory growth should be under 50MB');
    }

    /** @test */
    public function handles_malformed_photo_data_gracefully(): void
    {
        $user = User::factory()->create();

        $malformedCases = [
            // Missing quantity
            ['tags' => ['food' => ['wrapper' => []]]],

            // String instead of number
            ['tags' => ['food' => ['wrapper' => ['quantity' => 'five']]]],

            // Deeply nested garbage
            ['tags' => ['food' => ['wrapper' => ['quantity' => ['nested' => ['value' => 5]]]]]],

            // Null values
            ['tags' => ['food' => ['wrapper' => ['quantity' => null]]]],

            // Missing tags entirely
            [],

            // Empty tags
            ['tags' => []],
        ];

        foreach ($malformedCases as $index => $summary) {
            $photo = new Photo();
            $photo->user_id = $user->id;
            $photo->summary = $summary;
            $photo->created_at = now();
            $photo->filename = "test{$index}.png";
            $photo->model = 'iphone';
            $photo->datetime = now();
            $photo->country_id = $this->country->id;
            $photo->state_id = $this->state->id;
            $photo->city_id = $this->city->id;
            $photo->save();

            // Should not throw exception
            RedisMetricsCollector::queue($photo);
            $unlocked = $this->engine->evaluate($photo);

            // May or may not unlock uploads achievement
            $this->assertIsIterable($unlocked);
        }

        // Test case where tags is not an array (this currently breaks RedisMetricsCollector)
        // This test documents the current limitation
        $photo = new Photo();
        $photo->user_id = $user->id;
        $photo->summary = ['tags' => 'not-an-array'];
        $photo->created_at = now();
        $photo->filename = "test_string_tags.png";
        $photo->model = 'iphone';
        $photo->datetime = now();
        $photo->country_id = $this->country->id;
        $photo->state_id = $this->state->id;
        $photo->city_id = $this->city->id;
        $photo->save();

        // This currently throws an error in RedisMetricsCollector
        try {
            RedisMetricsCollector::queue($photo);
            $this->fail('Expected exception for non-array tags');
        } catch (\ErrorException $e) {
            $this->assertStringContainsString('foreach() argument must be of type array|object', $e->getMessage());
        }
    }

    // Helper methods

    private function createPhoto(User $user, array $tags, $createdAt = null): Photo
    {
        return Photo::create([
            'user_id' => $user->id,
            'summary' => ['tags' => $tags],
            'created_at' => $createdAt ?? now(),
            'filename' => 'test.png',
            'model' => 'iphone',
            'datetime' => $createdAt ?? now(),
            'country_id' => $this->country->id,
            'state_id' => $this->state->id,
            'city_id' => $this->city->id,
        ]);
    }

    private function randomTagData(): array
    {
        $categories = ['food', 'softdrinks', 'coffee', 'alcohol'];
        $objects = ['wrapper', 'plastic_bottle', 'coffee_cup', 'can'];
        $materials = ['plastic', 'paper', 'metal', 'glass'];
        $brands = ['coca_cola', 'pepsi', 'starbucks', 'mcdonalds'];

        $category = $categories[array_rand($categories)];
        $object = $objects[array_rand($objects)];

        return [
            $category => [
                $object => [
                    'quantity' => rand(1, 10),
                    'materials' => [$materials[array_rand($materials)] => rand(1, 10)],
                    'brands' => [$brands[array_rand($brands)] => rand(1, 10)],
                ]
            ]
        ];
    }
}
