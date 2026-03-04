<?php

namespace Tests\Feature;

use App\Enums\LocationType;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CategoryObject;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Photo;
use App\Models\Users\User;
use App\Services\Redis\RedisKeys;
use Database\Seeders\Tags\GenerateTagsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * End-to-end lifecycle test covering: account creation → upload → tagging →
 * XP → leaderboards → locations → edits → deletes.
 *
 * Catches integration bugs at system boundaries where individual components
 * work in isolation but produce incorrect state when chained together.
 *
 * IMPORTANT: No Event::fake(), no MetricsService mocks. The whole point is
 * testing the real pipeline end-to-end.
 */
class UserLifecycleTest extends TestCase
{
    use HasPhotoUploads;

    private ?CategoryObject $buttsClo = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPhotoUploads();
        $this->seed(GenerateTagsSeeder::class);

        $smokingCategory = Category::where('key', 'smoking')->first();
        $buttsObject = LitterObject::where('key', 'butts')->first();
        $this->buttsClo = CategoryObject::where('category_id', $smokingCategory->id)
            ->where('litter_object_id', $buttsObject->id)
            ->first();
    }

    // =========================================================================
    // Full Lifecycle
    // =========================================================================

    /**
     * Full user lifecycle: upload → tag → edit → delete → re-upload.
     *
     * Verifies that MySQL metrics, Redis stats/leaderboards, profile API,
     * and leaderboard API all stay consistent through the full journey.
     */
    public function test_full_user_lifecycle(): void
    {
        $this->assertNotNull($this->buttsClo, 'CLO for smoking/butts must exist after seeding');

        $user = User::factory()->create([
            'xp' => 0,
            'total_images' => 0,
            'verification_required' => false, // trusted user → auto-verify
            'picked_up' => null, // no default → remaining=true → no PickedUp bonus
        ]);

        $userScope = RedisKeys::user($user->id);
        $globalScope = RedisKeys::global();

        // === BASELINE ===
        $this->assertEquals(0, $user->xp);
        $this->assertFalse(Redis::zScore(RedisKeys::xpRanking($globalScope), (string) $user->id));

        // === STEP 1: Upload photo ===
        $photoId = $this->uploadPhoto($user);
        $photo = Photo::find($photoId);

        $this->assertNotNull($photo);
        $this->assertNull($photo->summary, 'No tags yet');
        $this->assertNotNull($photo->processed_at, 'recordUploadMetrics should set processed_at');
        $this->assertEquals(5, (int) $photo->processed_xp);

        $user->refresh();
        $this->assertEquals(5, $user->xp, 'Upload awards 5 XP to users.xp');

        // Redis user stats
        $this->assertEquals(1, (int) Redis::hGet(RedisKeys::stats($userScope), 'uploads'));
        $this->assertEquals(5, (int) Redis::hGet(RedisKeys::stats($userScope), 'xp'));

        // MySQL metrics
        $this->assertMetricsRow($user->id, 1, 5, 0);

        $countryId = $photo->country_id;
        $stateId = $photo->state_id;
        $cityId = $photo->city_id;

        // === STEP 2: Add tags — 3 cigarette butts ===
        // photo.xp = tag XP only = 3. Total XP (upload+tag) = 8.
        $this->tagPhoto($user, $photoId, 3);

        $photo->refresh();
        $expectedXp = 8; // total: upload(5) + tag(3)
        $this->assertNotNull($photo->summary, 'Summary generated after tagging');
        $this->assertEquals(3, $photo->xp, 'Photo XP = tag only (3)');
        $this->assertEquals($expectedXp, (int) $photo->processed_xp, 'processed_xp = upload+tag');

        // Redis should have full XP (upload base delta + tag XP delta)
        $this->assertRedisUserXp($user->id, $expectedXp);
        $this->assertRedisLeaderboardXp($user->id, $expectedXp);

        // MySQL metrics: all-time global
        $this->assertMetricsRow($user->id, 1, $expectedXp, 3);

        // Location-scoped metrics should exist
        $this->assertLocationMetrics($user->id, $countryId, $stateId, $cityId);

        // === STEP 3: Profile stats ===
        $profileResponse = $this->actingAs($user)->getJson('/api/user/profile/index');
        $profileResponse->assertOk();
        $this->assertEquals(1, $profileResponse->json('stats.uploads'), 'Profile: 1 upload');
        $this->assertEquals($expectedXp, $profileResponse->json('stats.xp'), 'Profile XP from Redis');
        $this->assertEquals(3, $profileResponse->json('stats.litter'), 'Profile litter: 3');
        $this->assertEquals(1, $profileResponse->json('rank.global_position'), 'Rank 1 (only user)');
        $this->assertEquals(1, $profileResponse->json('locations.countries'), '1 country');

        // === STEP 4: Today leaderboard ===
        $lbResponse = $this->actingAs($user)->getJson('/api/leaderboard?timeFilter=today');
        $lbResponse->assertOk();
        $this->assertEquals(1, $lbResponse->json('total'), '1 user on today leaderboard');
        $this->assertEquals(1, $lbResponse->json('currentUserRank'), 'User is rank 1');
        $this->assertCount(1, $lbResponse->json('users'));

        // === STEP 5: Location recognition (Redis ZSETs) ===
        $this->assertNotFalse(
            Redis::zScore(RedisKeys::xpRanking(RedisKeys::country($countryId)), (string) $user->id),
            'User on country leaderboard'
        );
        $this->assertNotFalse(
            Redis::zScore(RedisKeys::xpRanking(RedisKeys::state($stateId)), (string) $user->id),
            'User on state leaderboard'
        );
        $this->assertNotFalse(
            Redis::zScore(RedisKeys::xpRanking(RedisKeys::city($cityId)), (string) $user->id),
            'User on city leaderboard'
        );

        // === STEP 6: Replace tags — increase to 5 butts ===
        // photo.xp = tag only: 5×1 = 5. Total (upload+tag) = 10.
        $this->replaceTagsOnPhoto($user, $photoId, 5);

        $photo->refresh();
        $expectedXpUp = 10;
        $this->assertEquals(5, $photo->xp, 'Photo XP = tag only (5) after increase');
        $this->assertEquals($expectedXpUp, (int) $photo->processed_xp, 'processed_xp = upload+tag (10)');

        $this->assertRedisUserXp($user->id, $expectedXpUp);
        $this->assertRedisLeaderboardXp($user->id, $expectedXpUp);
        $this->assertMetricsRow($user->id, 1, $expectedXpUp, 5);

        // === STEP 7: Replace tags — decrease to 2 butts ===
        // photo.xp = tag only: 2×1 = 2. Total (upload+tag) = 7.
        $this->replaceTagsOnPhoto($user, $photoId, 2);

        $photo->refresh();
        $expectedXpDown = 7;
        $this->assertEquals(2, $photo->xp, 'Photo XP = tag only (2) after decrease');
        $this->assertEquals($expectedXpDown, (int) $photo->processed_xp, 'processed_xp = upload+tag (7)');

        $this->assertRedisUserXp($user->id, $expectedXpDown);
        $this->assertRedisLeaderboardXp($user->id, $expectedXpDown);
        $this->assertMetricsRow($user->id, 1, $expectedXpDown, 2);

        // === STEP 8: Delete photo ===
        $deleteResponse = $this->actingAs($user)
            ->postJson('/api/profile/photos/delete', ['photoid' => $photoId]);
        $deleteResponse->assertOk();

        $this->assertSoftDeleted('photos', ['id' => $photoId]);

        // Redis: user pruned from leaderboard
        $this->assertFalse(
            Redis::zScore(RedisKeys::xpRanking($globalScope), (string) $user->id),
            'Pruned from global leaderboard'
        );
        $this->assertEquals(0, (int) Redis::hGet(RedisKeys::stats($userScope), 'xp'));

        // MySQL metrics zeroed
        $this->assertMetricsRow($user->id, 0, 0, 0);

        // Profile: everything zero
        $profileAfterDelete = $this->actingAs($user)->getJson('/api/user/profile/index');
        $profileAfterDelete->assertOk();
        $this->assertEquals(0, $profileAfterDelete->json('stats.xp'), 'Profile XP 0 after delete');

        // Today leaderboard: empty
        $lbAfterDelete = $this->actingAs($user)->getJson('/api/leaderboard?timeFilter=today');
        $this->assertEquals(0, $lbAfterDelete->json('total'), 'Leaderboard empty after delete');

        // === STEP 9: Upload + tag second photo (1 butt) ===
        $photo2Id = $this->uploadPhoto($user);

        $this->tagPhoto($user, $photo2Id, 1);

        $photo2 = Photo::find($photo2Id);
        $expectedXp2 = 6; // total: upload(5) + tag(1)
        $this->assertEquals(1, $photo2->xp, 'Second photo XP = tag only (1)');

        // === STEP 10: Final consistency — all metric sources agree ===
        $this->assertRedisUserXp($user->id, $expectedXp2);
        $this->assertRedisLeaderboardXp($user->id, $expectedXp2);
        $this->assertMetricsRow($user->id, 1, $expectedXp2, 1);

        // Profile API agrees
        $finalProfile = $this->actingAs($user)->getJson('/api/user/profile/index');
        $this->assertEquals($expectedXp2, $finalProfile->json('stats.xp'), 'Profile XP matches');
        $this->assertEquals(1, $finalProfile->json('stats.uploads'), 'Profile shows 1 upload');

        // Leaderboard agrees
        $finalLb = $this->actingAs($user)->getJson('/api/leaderboard?timeFilter=today');
        $this->assertEquals(1, $finalLb->json('total'));
        $this->assertEquals(1, $finalLb->json('currentUserRank'));
    }

    // =========================================================================
    // users.xp Consistency Tests — Exposed Bug
    // =========================================================================

    /**
     * users.xp should be updated at tag time: upload(5) + tag XP.
     * MetricsService::doUpdate() syncs users.xp via delta.
     */
    public function test_users_xp_matches_photo_xp_after_tagging(): void
    {
        $user = User::factory()->create([
            'xp' => 0,
            'verification_required' => false,
            'picked_up' => null,
        ]);

        $photoId = $this->uploadPhoto($user);

        $user->refresh();
        $this->assertEquals(5, $user->xp, 'Upload: users.xp = 5');

        $this->tagPhoto($user, $photoId, 3);

        $photo = Photo::find($photoId);
        $this->assertEquals(3, $photo->xp, 'Photo XP = tag only (3)');

        $user->refresh();
        $this->assertEquals(8, $user->xp, 'users.xp = upload(5) + tag(3) = 8');
    }

    /**
     * users.xp should track correctly through replace operations.
     */
    public function test_users_xp_tracks_tag_replacements(): void
    {
        $user = User::factory()->create([
            'xp' => 0,
            'verification_required' => false,
            'picked_up' => null,
        ]);

        $photoId = $this->uploadPhoto($user);

        // Tag with 3 butts: photo.xp = 8
        $this->tagPhoto($user, $photoId, 3);
        $user->refresh();
        $this->assertEquals(8, $user->xp, 'users.xp should be 8 after initial tag');

        // Replace with 5 butts: photo.xp = 10
        $this->replaceTagsOnPhoto($user, $photoId, 5);
        $user->refresh();
        $this->assertEquals(10, $user->xp, 'users.xp should be 10 after tag increase');

        // Replace with 2 butts: photo.xp = 7
        $this->replaceTagsOnPhoto($user, $photoId, 2);
        $user->refresh();
        $this->assertEquals(7, $user->xp, 'users.xp should be 7 after tag decrease');

        // Delete: users.xp should be 0
        $this->actingAs($user)->postJson('/api/profile/photos/delete', ['photoid' => $photoId]);
        $user->refresh();
        $this->assertEquals(0, $user->xp, 'users.xp should be 0 after delete');
    }

    // =========================================================================
    // Upload without tags — baseline
    // =========================================================================

    public function test_upload_creates_photo_with_upload_metrics(): void
    {
        $user = User::factory()->create([
            'xp' => 0,
            'total_images' => 0,
            'picked_up' => null,
        ]);

        $photoId = $this->uploadPhoto($user);
        $photo = Photo::find($photoId);

        // Photo state
        $this->assertNotNull($photo);
        $this->assertNull($photo->summary, 'No summary without tags');
        $this->assertNotNull($photo->processed_at, 'recordUploadMetrics sets processed_at');
        $this->assertEquals(5, (int) $photo->processed_xp);

        // users.xp
        $user->refresh();
        $this->assertEquals(5, $user->xp);

        // Redis
        $userScope = RedisKeys::user($user->id);
        $this->assertEquals(1, (int) Redis::hGet(RedisKeys::stats($userScope), 'uploads'));
        $this->assertEquals(5, (int) Redis::hGet(RedisKeys::stats($userScope), 'xp'));

        // MySQL metrics
        $this->assertMetricsRow($user->id, 1, 5, 0);
    }

    // =========================================================================
    // Delete photo — XP reversal
    // =========================================================================

    public function test_delete_reverses_metrics_and_redis(): void
    {
        $user = User::factory()->create([
            'xp' => 0,
            'total_images' => 0,
            'verification_required' => false,
            'picked_up' => null,
        ]);

        $photoId = $this->uploadPhoto($user);
        $this->tagPhoto($user, $photoId, 3);

        $photo = Photo::find($photoId);
        $this->assertEquals(3, $photo->xp, 'Photo XP = tag only (3)');

        $globalScope = RedisKeys::global();
        $userScope = RedisKeys::user($user->id);

        // Pre-delete: Redis has XP
        $this->assertNotFalse(Redis::zScore(RedisKeys::xpRanking($globalScope), (string) $user->id));

        // Delete
        $response = $this->actingAs($user)
            ->postJson('/api/profile/photos/delete', ['photoid' => $photoId]);
        $response->assertOk();

        $this->assertSoftDeleted('photos', ['id' => $photoId]);

        // Redis: pruned from leaderboard
        $this->assertFalse(Redis::zScore(RedisKeys::xpRanking($globalScope), (string) $user->id));
        $this->assertEquals(0, (int) Redis::hGet(RedisKeys::stats($userScope), 'xp'));
        $this->assertEquals(0, (int) Redis::hGet(RedisKeys::stats($userScope), 'uploads'));

        // MySQL metrics zeroed
        $this->assertMetricsRow($user->id, 0, 0, 0);

        // users.xp and total_images
        $user->refresh();
        $this->assertEquals(0, $user->xp, 'users.xp should be 0 after delete');
        $this->assertEquals(0, $user->total_images);
    }

    // =========================================================================
    // Leaderboard appearance
    // =========================================================================

    public function test_tagged_user_appears_on_today_leaderboard(): void
    {
        $user = User::factory()->create([
            'xp' => 0,
            'verification_required' => false,
            'picked_up' => null,
        ]);

        $photoId = $this->uploadPhoto($user);
        $this->tagPhoto($user, $photoId, 2);

        // Today leaderboard
        $response = $this->actingAs($user)->getJson('/api/leaderboard?timeFilter=today');
        $response->assertOk();
        $this->assertEquals(1, $response->json('total'));
        $this->assertEquals(1, $response->json('currentUserRank'));

        $users = $response->json('users');
        $this->assertCount(1, $users);
        $this->assertEquals($user->id, $users[0]['user_id']);

        // All-time leaderboard
        $allTimeResponse = $this->actingAs($user)->getJson('/api/leaderboard?timeFilter=all-time');
        $allTimeResponse->assertOk();
        $this->assertEquals(1, $allTimeResponse->json('total'));
        $this->assertEquals(1, $allTimeResponse->json('currentUserRank'));
    }

    /**
     * After deleting the only photo, user should disappear from leaderboards.
     */
    public function test_deleted_user_disappears_from_leaderboard(): void
    {
        $user = User::factory()->create([
            'xp' => 0,
            'verification_required' => false,
            'picked_up' => null,
        ]);

        $photoId = $this->uploadPhoto($user);
        $this->tagPhoto($user, $photoId, 2);

        // Verify user is on leaderboard
        $response = $this->actingAs($user)->getJson('/api/leaderboard?timeFilter=today');
        $this->assertEquals(1, $response->json('total'));

        // Delete
        $this->actingAs($user)->postJson('/api/profile/photos/delete', ['photoid' => $photoId]);

        // User gone from leaderboard
        $response = $this->actingAs($user)->getJson('/api/leaderboard?timeFilter=today');
        $this->assertEquals(0, $response->json('total'));
        $this->assertNull($response->json('currentUserRank'));
    }

    // =========================================================================
    // Location recognition
    // =========================================================================

    public function test_user_recognized_at_photo_locations(): void
    {
        $user = User::factory()->create([
            'xp' => 0,
            'verification_required' => false,
            'picked_up' => null,
        ]);

        $photoId = $this->uploadPhoto($user);
        $this->tagPhoto($user, $photoId, 2);

        $photo = Photo::find($photoId);
        $expectedXp = 7; // total: upload(5) + tag(2)

        // Country scope
        $countryScope = RedisKeys::country($photo->country_id);
        $this->assertEquals(
            (float) $expectedXp,
            Redis::zScore(RedisKeys::xpRanking($countryScope), (string) $user->id),
            'Country leaderboard XP'
        );

        // State scope
        $stateScope = RedisKeys::state($photo->state_id);
        $this->assertEquals(
            (float) $expectedXp,
            Redis::zScore(RedisKeys::xpRanking($stateScope), (string) $user->id),
            'State leaderboard XP'
        );

        // City scope
        $cityScope = RedisKeys::city($photo->city_id);
        $this->assertEquals(
            (float) $expectedXp,
            Redis::zScore(RedisKeys::xpRanking($cityScope), (string) $user->id),
            'City leaderboard XP'
        );

        // Profile shows location counts
        $profileResponse = $this->actingAs($user)->getJson('/api/user/profile/index');
        $this->assertEquals(1, $profileResponse->json('locations.countries'));
        $this->assertEquals(1, $profileResponse->json('locations.states'));
        $this->assertEquals(1, $profileResponse->json('locations.cities'));
    }

    // =========================================================================
    // Tag replacement — delta calculation
    // =========================================================================

    public function test_replace_tags_adjusts_metrics_by_delta(): void
    {
        $user = User::factory()->create([
            'xp' => 0,
            'verification_required' => false,
            'picked_up' => null,
        ]);

        $photoId = $this->uploadPhoto($user);
        $this->tagPhoto($user, $photoId, 3);

        $photo = Photo::find($photoId);
        $this->assertEquals(3, $photo->xp, 'Initial: tag only (3)');

        // Increase to 5 butts
        $this->replaceTagsOnPhoto($user, $photoId, 5);
        $photo->refresh();
        $this->assertEquals(5, $photo->xp, 'After increase: tag only (5)');
        $this->assertRedisLeaderboardXp($user->id, 10);
        $this->assertMetricsRow($user->id, 1, 10, 5);

        // Decrease to 1 butt
        $this->replaceTagsOnPhoto($user, $photoId, 1);
        $photo->refresh();
        $this->assertEquals(1, $photo->xp, 'After decrease: tag only (1)');
        $this->assertRedisLeaderboardXp($user->id, 6);
        $this->assertMetricsRow($user->id, 1, 6, 1);
    }

    // =========================================================================
    // Profile stats consistency
    // =========================================================================

    public function test_profile_stats_reflect_current_state(): void
    {
        $user = User::factory()->create([
            'xp' => 0,
            'total_images' => 0,
            'verification_required' => false,
            'picked_up' => null,
        ]);

        // Before anything
        $response = $this->actingAs($user)->getJson('/api/user/profile/index');
        $response->assertOk();
        $this->assertEquals(0, $response->json('stats.uploads'));
        $this->assertEquals(0, $response->json('stats.xp'));
        $this->assertEquals(0, $response->json('stats.litter'));

        // After upload
        $photoId = $this->uploadPhoto($user);
        $response = $this->actingAs($user)->getJson('/api/user/profile/index');
        $this->assertEquals(1, $response->json('stats.uploads'), 'After upload: 1 upload');
        $this->assertEquals(5, $response->json('stats.xp'), 'After upload: 5 XP');
        $this->assertEquals(0, $response->json('stats.litter'), 'After upload: 0 litter');

        // After tagging
        $this->tagPhoto($user, $photoId, 4);
        $response = $this->actingAs($user)->getJson('/api/user/profile/index');
        $this->assertEquals(1, $response->json('stats.uploads'), 'After tag: still 1 upload');
        $this->assertEquals(9, $response->json('stats.xp'), 'After tag: 5+4 = 9 XP');
        $this->assertEquals(4, $response->json('stats.litter'), 'After tag: 4 litter');

        // After delete
        $this->actingAs($user)->postJson('/api/profile/photos/delete', ['photoid' => $photoId]);
        $response = $this->actingAs($user)->getJson('/api/user/profile/index');
        $this->assertEquals(0, $response->json('stats.uploads'), 'After delete: 0 uploads');
        $this->assertEquals(0, $response->json('stats.xp'), 'After delete: 0 XP');
        $this->assertEquals(0, $response->json('stats.litter'), 'After delete: 0 litter');
    }

    // =========================================================================
    // Action Helpers
    // =========================================================================

    private function uploadPhoto(User $user): int
    {
        $imageAttributes = $this->getImageAndAttributes();

        $response = $this->actingAs($user)
            ->postJson('/api/v3/upload', [
                'photo' => $imageAttributes['file'],
                'lat' => $imageAttributes['latitude'],
                'lon' => $imageAttributes['longitude'],
                'date' => $imageAttributes['dateTime']->timestamp,
                'model' => 'test model',
            ]);

        $response->assertOk();
        $this->assertEquals(5, $response->json('xp_awarded'));

        return $response->json('photo_id');
    }

    private function tagPhoto(User $user, int $photoId, int $quantity): void
    {
        $response = $this->actingAs($user)
            ->postJson('/api/v3/tags', [
                'photo_id' => $photoId,
                'tags' => [
                    ['category_litter_object_id' => $this->buttsClo->id, 'quantity' => $quantity],
                ],
            ]);

        $response->assertOk();
    }

    private function replaceTagsOnPhoto(User $user, int $photoId, int $quantity): void
    {
        $response = $this->actingAs($user)
            ->putJson('/api/v3/tags', [
                'photo_id' => $photoId,
                'tags' => [
                    ['category_litter_object_id' => $this->buttsClo->id, 'quantity' => $quantity],
                ],
            ]);

        $response->assertOk();
    }

    // =========================================================================
    // Assertion Helpers
    // =========================================================================

    private function assertMetricsRow(int $userId, int $uploads, int $xp, int $litter): void
    {
        $row = DB::table('metrics')
            ->where('timescale', 0)
            ->where('location_type', LocationType::Global->value)
            ->where('location_id', 0)
            ->where('user_id', $userId)
            ->where('year', 0)
            ->where('month', 0)
            ->first();

        $this->assertNotNull($row, 'All-time global per-user metrics row should exist');
        $this->assertEquals($uploads, (int) $row->uploads, 'Metrics uploads');
        $this->assertEquals($xp, (int) $row->xp, 'Metrics XP');
        $this->assertEquals($litter, (int) $row->litter, 'Metrics litter');
    }

    private function assertRedisUserXp(int $userId, int $expectedXp): void
    {
        $userScope = RedisKeys::user($userId);
        $this->assertEquals(
            $expectedXp,
            (int) Redis::hGet(RedisKeys::stats($userScope), 'xp'),
            'Redis user stats XP'
        );
    }

    private function assertRedisLeaderboardXp(int $userId, int $expectedXp): void
    {
        $globalScope = RedisKeys::global();
        $this->assertEquals(
            (float) $expectedXp,
            Redis::zScore(RedisKeys::xpRanking($globalScope), (string) $userId),
            'Redis global leaderboard XP'
        );
    }

    private function assertLocationMetrics(int $userId, int $countryId, int $stateId, int $cityId): void
    {
        foreach ([
            ['type' => LocationType::Country->value, 'id' => $countryId, 'label' => 'country'],
            ['type' => LocationType::State->value, 'id' => $stateId, 'label' => 'state'],
            ['type' => LocationType::City->value, 'id' => $cityId, 'label' => 'city'],
        ] as $scope) {
            $row = DB::table('metrics')
                ->where('timescale', 0)
                ->where('location_type', $scope['type'])
                ->where('location_id', $scope['id'])
                ->where('user_id', $userId)
                ->where('year', 0)
                ->where('month', 0)
                ->first();

            $this->assertNotNull($row, "Metrics row for {$scope['label']} should exist");
        }
    }
}
