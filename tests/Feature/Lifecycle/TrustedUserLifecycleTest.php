<?php

namespace Tests\Feature\Lifecycle;

use App\Enums\LocationType;
use App\Enums\VerificationStatus;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CategoryObject;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Photo;
use App\Models\Users\User;
use App\Services\Redis\RedisKeys;
use Database\Seeders\Tags\GenerateTagsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Tests\Feature\HasPhotoUploads;
use Tests\TestCase;

/**
 * Trusted user lifecycle: the user has verification_required=false.
 * Photos are auto-promoted to ADMIN_APPROVED at tag time.
 *
 * Walks through the real user journey: account → upload → tag → edit → delete → re-upload.
 * No Event::fake(), no MetricsService mocks — tests the real pipeline.
 */
class TrustedUserLifecycleTest extends TestCase
{
    use HasPhotoUploads;

    private ?CategoryObject $buttsClo = null;
    private ?CategoryObject $wrappersClo = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPhotoUploads();
        $this->seed(GenerateTagsSeeder::class);

        // Resolve CLOs for test tags
        $smokingCategory = Category::where('key', 'smoking')->first();
        $buttsObject = LitterObject::where('key', 'butts')->first();
        $this->buttsClo = CategoryObject::where('category_id', $smokingCategory->id)
            ->where('litter_object_id', $buttsObject->id)
            ->first();

        $foodCategory = Category::where('key', 'food')->first();
        $wrappersObject = LitterObject::where('key', 'wrapper')->first();
        if ($foodCategory && $wrappersObject) {
            $this->wrappersClo = CategoryObject::where('category_id', $foodCategory->id)
                ->where('litter_object_id', $wrappersObject->id)
                ->first();
        }
    }

    /**
     * Full trusted user lifecycle: signup → upload → tag → edit tags → replace tags → delete → re-upload.
     *
     * At each step, verify that MySQL metrics, Redis stats/leaderboards, users.xp,
     * profile API, and leaderboard API all agree.
     */
    public function test_trusted_user_full_lifecycle(): void
    {
        $this->assertNotNull($this->buttsClo, 'CLO for smoking/butts must exist after seeding');

        // === STEP 1: Create account — clean slate ===
        $user = User::factory()->create([
            'xp' => 0,
            'total_images' => 0,
            'verification_required' => false, // trusted
            'picked_up' => null,
        ]);

        $userScope = RedisKeys::user($user->id);
        $globalScope = RedisKeys::global();

        // Baseline: no XP, not on leaderboard
        $this->assertEquals(0, $user->xp);
        $this->assertFalse(Redis::zScore(RedisKeys::xpRanking($globalScope), (string) $user->id));

        // Profile: zeros across the board
        $profile = $this->actingAs($user)->getJson('/api/user/profile/index');
        $profile->assertOk();
        $this->assertEquals(0, $profile->json('stats.uploads'));
        $this->assertEquals(0, $profile->json('stats.xp'));
        $this->assertEquals(0, $profile->json('stats.litter'));

        // === STEP 2: Upload a photo — 5 XP immediately, no litter yet ===
        $photoId = $this->uploadPhoto($user);
        $photo = Photo::find($photoId);

        // Photo state: exists, no tags, upload metrics recorded
        $this->assertNotNull($photo);
        $this->assertNull($photo->summary, 'No tags yet');
        $this->assertNotNull($photo->processed_at, 'recordUploadMetrics sets processed_at');
        $this->assertEquals(5, (int) $photo->processed_xp, 'processed_xp = upload only (5)');

        // User XP: upload base awarded
        $user->refresh();
        $this->assertEquals(5, $user->xp, 'Upload awards 5 XP');

        // Redis: user appears with upload XP
        $this->assertEquals(1, (int) Redis::hGet(RedisKeys::stats($userScope), 'uploads'));
        $this->assertEquals(5, (int) Redis::hGet(RedisKeys::stats($userScope), 'xp'));
        $this->assertEquals(5.0, Redis::zScore(RedisKeys::xpRanking($globalScope), (string) $user->id));

        // MySQL metrics: 1 upload, 5 XP, 0 litter
        $this->assertMetricsRow($user->id, 1, 5, 0);

        // Profile reflects upload
        $profile = $this->actingAs($user)->getJson('/api/user/profile/index');
        $this->assertEquals(1, $profile->json('stats.uploads'));
        $this->assertEquals(5, $profile->json('stats.xp'));
        $this->assertEquals(0, $profile->json('stats.litter'));

        // Leaderboard: user visible with upload XP
        $lb = $this->actingAs($user)->getJson('/api/leaderboard?timeFilter=today');
        $lb->assertOk();
        $this->assertEquals(1, $lb->json('total'));
        $this->assertEquals(1, $lb->json('currentUserRank'));

        $countryId = $photo->country_id;
        $stateId = $photo->state_id;
        $cityId = $photo->city_id;

        // === STEP 3: Tag photo — 3 cigarette butts (3 XP) ===
        // photo.xp = tag only = 3. Total: upload(5) + tag(3) = 8
        $this->tagPhoto($user, $photoId, 3);

        $photo->refresh();
        $this->assertEquals(3, $photo->xp, 'Photo XP = tag only (3)');
        $this->assertEquals(8, (int) $photo->processed_xp, 'processed_xp = upload+tag (8)');
        $this->assertNotNull($photo->summary, 'Summary generated after tagging');

        // Trusted user: auto-promoted to ADMIN_APPROVED
        $this->assertEquals(
            VerificationStatus::ADMIN_APPROVED->value,
            $photo->verified->value,
            'Trusted user photo auto-approved'
        );

        // User XP synced
        $user->refresh();
        $this->assertEquals(8, $user->xp, 'users.xp = 5 + 3');

        // Redis: full XP
        $this->assertRedisUserXp($user->id, 8);
        $this->assertRedisLeaderboardXp($user->id, 8);

        // MySQL metrics: 1 upload, 8 XP, 3 litter
        $this->assertMetricsRow($user->id, 1, 8, 3);

        // Location scoped: user on country/state/city leaderboards
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

        // Profile: shows tags and locations
        $profile = $this->actingAs($user)->getJson('/api/user/profile/index');
        $this->assertEquals(8, $profile->json('stats.xp'));
        $this->assertEquals(3, $profile->json('stats.litter'));
        $this->assertEquals(1, $profile->json('rank.global_position'));
        $this->assertEquals(1, $profile->json('locations.countries'));

        // === STEP 4: Replace tags — increase to 5 butts ===
        // photo.xp = 5, total: upload(5) + tag(5) = 10
        $this->replaceTagsOnPhoto($user, $photoId, 5);

        $photo->refresh();
        $this->assertEquals(5, $photo->xp, 'Photo XP = tag only (5)');
        $this->assertEquals(10, (int) $photo->processed_xp, 'processed_xp = 10');

        $user->refresh();
        $this->assertEquals(10, $user->xp);

        $this->assertRedisUserXp($user->id, 10);
        $this->assertRedisLeaderboardXp($user->id, 10);
        $this->assertMetricsRow($user->id, 1, 10, 5);

        // === STEP 5: Replace tags — decrease to 2 butts ===
        // photo.xp = 2, total: upload(5) + tag(2) = 7
        $this->replaceTagsOnPhoto($user, $photoId, 2);

        $photo->refresh();
        $this->assertEquals(2, $photo->xp, 'Photo XP = tag only (2)');
        $this->assertEquals(7, (int) $photo->processed_xp, 'processed_xp = 7');

        $user->refresh();
        $this->assertEquals(7, $user->xp);

        $this->assertRedisUserXp($user->id, 7);
        $this->assertRedisLeaderboardXp($user->id, 7);
        $this->assertMetricsRow($user->id, 1, 7, 2);

        // === STEP 6: Delete photo — everything reverses to zero ===
        $deleteResponse = $this->actingAs($user)
            ->postJson('/api/profile/photos/delete', ['photoid' => $photoId]);
        $deleteResponse->assertOk();

        $this->assertSoftDeleted('photos', ['id' => $photoId]);

        // Redis: pruned from leaderboard
        $this->assertFalse(
            Redis::zScore(RedisKeys::xpRanking($globalScope), (string) $user->id),
            'Pruned from global leaderboard after delete'
        );
        $this->assertEquals(0, (int) Redis::hGet(RedisKeys::stats($userScope), 'xp'));
        $this->assertEquals(0, (int) Redis::hGet(RedisKeys::stats($userScope), 'uploads'));

        // MySQL metrics: zeroed
        $this->assertMetricsRow($user->id, 0, 0, 0);

        // users.xp: zero
        $user->refresh();
        $this->assertEquals(0, $user->xp, 'users.xp = 0 after delete');

        // Profile: everything zero
        $profile = $this->actingAs($user)->getJson('/api/user/profile/index');
        $this->assertEquals(0, $profile->json('stats.xp'));
        $this->assertEquals(0, $profile->json('stats.uploads'));
        $this->assertEquals(0, $profile->json('stats.litter'));

        // Leaderboard: empty
        $lb = $this->actingAs($user)->getJson('/api/leaderboard?timeFilter=today');
        $this->assertEquals(0, $lb->json('total'));

        // Location leaderboards: pruned
        $this->assertFalse(
            Redis::zScore(RedisKeys::xpRanking(RedisKeys::country($countryId)), (string) $user->id),
            'Pruned from country leaderboard after delete'
        );

        // === STEP 7: Re-upload and tag — fresh start ===
        $photo2Id = $this->uploadPhoto($user);
        $this->tagPhoto($user, $photo2Id, 1);

        $photo2 = Photo::find($photo2Id);
        $expectedXp = 6; // upload(5) + tag(1)

        $this->assertEquals(1, $photo2->xp, 'New photo XP = tag only (1)');
        $this->assertEquals($expectedXp, (int) $photo2->processed_xp);

        $user->refresh();
        $this->assertEquals($expectedXp, $user->xp);

        // === STEP 8: Final consistency — all metric sources agree ===
        $this->assertRedisUserXp($user->id, $expectedXp);
        $this->assertRedisLeaderboardXp($user->id, $expectedXp);
        $this->assertMetricsRow($user->id, 1, $expectedXp, 1);

        $profile = $this->actingAs($user)->getJson('/api/user/profile/index');
        $this->assertEquals($expectedXp, $profile->json('stats.xp'));
        $this->assertEquals(1, $profile->json('stats.uploads'));
        $this->assertEquals(1, $profile->json('stats.litter'));

        $lb = $this->actingAs($user)->getJson('/api/leaderboard?timeFilter=today');
        $this->assertEquals(1, $lb->json('total'));
        $this->assertEquals(1, $lb->json('currentUserRank'));
    }

    /**
     * Multiple tags with different categories to verify XP arithmetic.
     */
    public function test_mixed_tags_xp_arithmetic(): void
    {
        if (! $this->wrappersClo) {
            $this->markTestSkipped('sweetwrappers CLO not available');
        }

        $user = User::factory()->create([
            'xp' => 0,
            'verification_required' => false,
            'picked_up' => null,
        ]);

        $photoId = $this->uploadPhoto($user);

        // Tag with both butts (3) and wrappers (2) = 5 objects = 5 XP
        $response = $this->actingAs($user)
            ->postJson('/api/v3/tags', [
                'photo_id' => $photoId,
                'tags' => [
                    ['category_litter_object_id' => $this->buttsClo->id, 'quantity' => 3],
                    ['category_litter_object_id' => $this->wrappersClo->id, 'quantity' => 2],
                ],
            ]);
        $response->assertOk();

        $photo = Photo::find($photoId);
        $this->assertEquals(5, $photo->xp, 'Photo XP = 3 + 2 = 5 (tag only)');

        $user->refresh();
        $this->assertEquals(10, $user->xp, 'users.xp = upload(5) + tag(5) = 10');

        $this->assertRedisLeaderboardXp($user->id, 10);
        $this->assertMetricsRow($user->id, 1, 10, 5);
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
}
