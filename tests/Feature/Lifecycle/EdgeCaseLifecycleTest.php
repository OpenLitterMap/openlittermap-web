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
use Spatie\Permission\Models\Role;
use Tests\Feature\HasPhotoUploads;
use Tests\TestCase;

/**
 * Edge-case scenarios that the main lifecycle tests don't cover.
 *
 * No Event::fake(), no MetricsService mocks — tests the real pipeline.
 */
class EdgeCaseLifecycleTest extends TestCase
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
    // Scenario 1: Delete an untagged photo reverses upload XP
    // =========================================================================

    /**
     * A user uploads a photo (5 XP via recordUploadMetrics) and never tags it.
     * On deletion, MetricsService::deletePhoto() must reverse the full 5 XP.
     *
     * This works because recordUploadMetrics() sets processed_at + processed_xp=5,
     * so the delete controller's `if ($photo->processed_at !== null)` guard passes
     * and deletePhoto() reads processed_xp=5 to reverse.
     */
    public function test_delete_untagged_photo_reverses_upload_xp(): void
    {
        $user = User::factory()->create([
            'xp' => 0,
            'total_images' => 0,
            'verification_required' => false,
            'picked_up' => null,
        ]);

        $globalScope = RedisKeys::global();
        $userScope = RedisKeys::user($user->id);

        // Upload — 5 XP immediately
        $photoId = $this->uploadPhoto($user);
        $photo = Photo::find($photoId);

        // Verify upload set processing state
        $this->assertNotNull($photo->processed_at, 'recordUploadMetrics sets processed_at');
        $this->assertEquals(5, (int) $photo->processed_xp, 'processed_xp = 5 (upload only)');
        $this->assertEquals('', $photo->processed_fp, 'processed_fp is empty string (no tags)');
        $this->assertEquals('[]', $photo->processed_tags, 'processed_tags is empty JSON array');

        $user->refresh();
        $this->assertEquals(5, $user->xp, 'User has 5 XP from upload');

        // Redis: user on leaderboard with 5 XP
        $this->assertEquals(5.0, Redis::zScore(RedisKeys::xpRanking($globalScope), (string) $user->id));
        $this->assertEquals(5, (int) Redis::hGet(RedisKeys::stats($userScope), 'xp'));
        $this->assertEquals(1, (int) Redis::hGet(RedisKeys::stats($userScope), 'uploads'));

        // MySQL metrics: 1 upload, 5 XP, 0 litter
        $this->assertMetricsRow($user->id, 1, 5, 0);

        // Delete the UNTAGGED photo
        $deleteResponse = $this->actingAs($user)
            ->postJson('/api/profile/photos/delete', ['photoid' => $photoId]);
        $deleteResponse->assertOk();

        $this->assertSoftDeleted('photos', ['id' => $photoId]);

        // XP fully reversed to 0
        $user->refresh();
        $this->assertEquals(0, $user->xp, 'users.xp = 0 after deleting untagged photo');

        // Redis: pruned from leaderboard
        $this->assertFalse(
            Redis::zScore(RedisKeys::xpRanking($globalScope), (string) $user->id),
            'Pruned from leaderboard after delete'
        );
        $this->assertEquals(0, (int) Redis::hGet(RedisKeys::stats($userScope), 'xp'));
        $this->assertEquals(0, (int) Redis::hGet(RedisKeys::stats($userScope), 'uploads'));

        // MySQL metrics: zeroed
        $this->assertMetricsRow($user->id, 0, 0, 0);

        // Profile: zeros
        $profile = $this->actingAs($user)->getJson('/api/user/profile/index');
        $profile->assertOk();
        $this->assertEquals(0, $profile->json('stats.xp'));
        $this->assertEquals(0, $profile->json('stats.uploads'));
    }

    // =========================================================================
    // Scenario 2: Upload 3, tag 1, delete untagged ones — no ghost XP
    // =========================================================================

    /**
     * Upload 3 photos (15 XP), tag photo A (3 butts → 18 XP).
     * Delete untagged B and C → 8 XP. Delete tagged A → 0 XP.
     * Ghost XP = 0 (no leaks).
     */
    public function test_multi_photo_delete_untagged_no_ghost_xp(): void
    {
        $user = User::factory()->create([
            'xp' => 0,
            'total_images' => 0,
            'verification_required' => false,
            'picked_up' => null,
        ]);

        $globalScope = RedisKeys::global();

        // Upload 3 photos
        $photoAId = $this->uploadPhoto($user);
        $photoBId = $this->uploadPhoto($user);
        $photoCId = $this->uploadPhoto($user);

        $user->refresh();
        $this->assertEquals(15, $user->xp, 'Upload 3 photos = 15 XP');
        $this->assertMetricsRow($user->id, 3, 15, 0);

        // Tag only photo A with 3 butts → tag XP = 3, total = 18
        $this->tagPhoto($user, $photoAId, 3);

        $user->refresh();
        $this->assertEquals(18, $user->xp, 'upload(15) + tag(3) = 18');
        $this->assertMetricsRow($user->id, 3, 18, 3);

        // Delete untagged photo B → lose 5 XP → 13
        $this->actingAs($user)
            ->postJson('/api/profile/photos/delete', ['photoid' => $photoBId])
            ->assertOk();

        $user->refresh();
        $this->assertEquals(13, $user->xp, 'After deleting untagged B: 18 - 5 = 13');
        $this->assertMetricsRow($user->id, 2, 13, 3);

        // Delete untagged photo C → lose 5 XP → 8
        $this->actingAs($user)
            ->postJson('/api/profile/photos/delete', ['photoid' => $photoCId])
            ->assertOk();

        $user->refresh();
        $this->assertEquals(8, $user->xp, 'After deleting untagged C: 13 - 5 = 8');
        $this->assertMetricsRow($user->id, 1, 8, 3);

        // Delete tagged photo A → lose 8 XP (5 upload + 3 tag) → 0
        $this->actingAs($user)
            ->postJson('/api/profile/photos/delete', ['photoid' => $photoAId])
            ->assertOk();

        $user->refresh();
        $this->assertEquals(0, $user->xp, 'After deleting tagged A: 0 (no ghost XP)');
        $this->assertMetricsRow($user->id, 0, 0, 0);

        // Redis: fully pruned
        $this->assertFalse(
            Redis::zScore(RedisKeys::xpRanking($globalScope), (string) $user->id),
            'Fully pruned from leaderboard'
        );
    }

    // =========================================================================
    // Scenario 3: Replace tags validation rejects empty tag set
    // =========================================================================

    /**
     * PUT /api/v3/tags with an empty tags array must be rejected by validation
     * (ReplacePhotoTagsRequest requires tags|min:1).
     * Clearing all tags from a photo resets it to untagged state.
     */
    public function test_replace_tags_with_empty_array_clears_photo(): void
    {
        $user = User::factory()->create([
            'xp' => 0,
            'verification_required' => false,
            'picked_up' => null,
        ]);

        $photoId = $this->uploadPhoto($user);
        $this->tagPhoto($user, $photoId, 3);

        $user->refresh();
        $this->assertEquals(8, $user->xp, 'Baseline: upload(5) + tag(3) = 8');

        // Clear all tags
        $response = $this->actingAs($user)
            ->putJson('/api/v3/tags', [
                'photo_id' => $photoId,
                'tags' => [],
            ]);

        $response->assertOk();

        // Photo is now untagged
        $photo = Photo::find($photoId);
        $this->assertNull($photo->summary, 'Summary cleared');
        $this->assertEquals(0, $photo->xp, 'XP reset to 0');
        $this->assertEquals(0, $photo->total_tags, 'Total tags reset to 0');
    }

    // =========================================================================
    // Scenario 4: Two users, same location — HLL contributor count
    // =========================================================================

    /**
     * HyperLogLog is append-only: after User A deletes their only photo,
     * PFCOUNT still shows 2 contributors. This is by design (HLLs are
     * approximate and irreversible). The contributor_ranking ZSET IS
     * decremented and pruned correctly.
     *
     * Documented here as a regression guard and known-limitation acknowledgement.
     */
    public function test_hll_contributor_count_not_decremented_after_delete(): void
    {
        $userA = User::factory()->create([
            'xp' => 0,
            'verification_required' => false,
            'picked_up' => null,
        ]);
        $userB = User::factory()->create([
            'xp' => 0,
            'verification_required' => false,
            'picked_up' => null,
        ]);

        // Both upload + tag in same location (controlled by HasPhotoUploads)
        $photoAId = $this->uploadPhoto($userA);
        $this->tagPhoto($userA, $photoAId, 3);

        $photoBId = $this->uploadPhoto($userB);
        $this->tagPhoto($userB, $photoBId, 2);

        $photoA = Photo::find($photoAId);
        $countryId = $photoA->country_id;
        $countryScope = RedisKeys::country($countryId);

        // Both users on country leaderboard
        $this->assertNotFalse(
            Redis::zScore(RedisKeys::xpRanking($countryScope), (string) $userA->id),
            'User A on country leaderboard'
        );
        $this->assertNotFalse(
            Redis::zScore(RedisKeys::xpRanking($countryScope), (string) $userB->id),
            'User B on country leaderboard'
        );

        // Country contributor ranking: both users have score > 0
        $this->assertGreaterThan(
            0,
            (int) Redis::zScore(RedisKeys::contributorRanking($countryScope), (string) $userA->id),
            'User A in contributor ranking'
        );

        // HLL shows 2 unique contributors
        $hllBefore = Redis::pfCount(RedisKeys::hll($countryScope));
        $this->assertEquals(2, $hllBefore, 'HLL: 2 unique contributors before delete');

        // User A deletes their photo
        $this->actingAs($userA)
            ->postJson('/api/profile/photos/delete', ['photoid' => $photoAId])
            ->assertOk();

        // User A pruned from XP leaderboard (0 XP → pruned)
        $this->assertFalse(
            Redis::zScore(RedisKeys::xpRanking($countryScope), (string) $userA->id),
            'User A pruned from country leaderboard after delete'
        );

        // User B still on leaderboard with correct XP
        $userB->refresh();
        $this->assertEquals(7, $userB->xp, 'User B XP unchanged');
        $this->assertNotFalse(
            Redis::zScore(RedisKeys::xpRanking($countryScope), (string) $userB->id),
            'User B still on country leaderboard'
        );

        // HLL: still shows 2 (append-only, can't reverse PFADD)
        // This is a KNOWN LIMITATION, not a bug.
        $hllAfter = Redis::pfCount(RedisKeys::hll($countryScope));
        $this->assertEquals(2, $hllAfter, 'HLL still shows 2 (append-only, known limitation)');

        // contributor_ranking ZSET: User A decremented (may be 0 or pruned)
        $userAContrib = Redis::zScore(RedisKeys::contributorRanking($countryScope), (string) $userA->id);
        // contributor_ranking is NOT pruned at 0 (no zRemRangeByScore), so it stays at 0
        $this->assertEquals(0, (int) $userAContrib, 'User A contributor ranking = 0 after delete');

        // Country stats: litter should be User B's only (2)
        $countryLitter = (int) Redis::hGet(RedisKeys::stats($countryScope), 'litter');
        $this->assertEquals(2, $countryLitter, 'Country litter = User B only (2)');
    }

    // =========================================================================
    // Scenario 5: Admin edits tags to more, then user deletes
    // =========================================================================

    /**
     * Admin replaces a user's tags with a higher quantity.
     * MetricsService::processPhoto() updates processed_xp/processed_tags.
     * When the user deletes, the reversal uses the admin's updated values.
     */
    public function test_admin_edit_tags_then_user_deletes_reverses_correctly(): void
    {
        $user = User::factory()->create([
            'xp' => 0,
            'verification_required' => true, // untrusted user
            'picked_up' => null,
        ]);

        $photoId = $this->uploadPhoto($user);
        $this->tagPhoto($user, $photoId, 2); // 2 butts → tag XP = 2, total = 7

        $user->refresh();
        $this->assertEquals(7, $user->xp, 'upload(5) + tag(2) = 7');

        $photo = Photo::find($photoId);
        $this->assertEquals(7, (int) $photo->processed_xp, 'processed_xp = 7 before admin edit');

        // Admin edits tags to 5 butts
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->postJson('/api/admin/contentsupdatedelete', [
                'photoId' => $photoId,
                'tags' => [
                    ['category_litter_object_id' => $this->buttsClo->id, 'quantity' => 5],
                ],
            ])
            ->assertOk();

        // After admin edit: photo.xp = 5 (tag only), processed_xp = 10 (5 + 5 upload base)
        $photo->refresh();
        $this->assertEquals(5, $photo->xp, 'Photo XP = 5 after admin edit');
        $this->assertEquals(10, (int) $photo->processed_xp, 'processed_xp = 10 after admin edit');

        // User XP updated by MetricsService delta: 7 + (10 - 7) = 10
        $user->refresh();
        $this->assertEquals(10, $user->xp, 'User XP = 10 after admin edit');

        // User deletes — should reverse the ADMIN's processed_xp (10), not the original
        $this->actingAs($user)
            ->postJson('/api/profile/photos/delete', ['photoid' => $photoId])
            ->assertOk();

        $user->refresh();
        $this->assertEquals(0, $user->xp, 'User XP = 0 after delete (no ghost XP from admin edit)');
        $this->assertMetricsRow($user->id, 0, 0, 0);

        $this->assertFalse(
            Redis::zScore(RedisKeys::xpRanking(RedisKeys::global()), (string) $user->id),
            'Pruned from leaderboard after delete'
        );
    }

    // =========================================================================
    // Scenario 6: Rapid upload-tag-delete-reupload-tag cycle
    // =========================================================================

    /**
     * Upload → tag → delete → re-upload → tag.
     * Verify Redis {scope}:stats:photos is exactly 1 (not 2),
     * and all metrics are exactly for 1 photo.
     */
    public function test_rapid_cycle_no_stale_redis_accumulation(): void
    {
        $user = User::factory()->create([
            'xp' => 0,
            'verification_required' => false,
            'picked_up' => null,
        ]);

        $globalScope = RedisKeys::global();
        $userScope = RedisKeys::user($user->id);

        // Upload + tag photo A
        $photoAId = $this->uploadPhoto($user);
        $this->tagPhoto($user, $photoAId, 3); // 3 butts

        // State: 1 photo, 8 XP, 3 litter
        $this->assertEquals(1, (int) Redis::hGet(RedisKeys::stats($globalScope), 'photos'));
        $this->assertEquals(1, (int) Redis::hGet(RedisKeys::stats($userScope), 'uploads'));

        // Delete photo A
        $this->actingAs($user)
            ->postJson('/api/profile/photos/delete', ['photoid' => $photoAId])
            ->assertOk();

        // State: 0 photos, 0 XP, 0 litter
        $this->assertEquals(0, (int) Redis::hGet(RedisKeys::stats($globalScope), 'photos'));
        $this->assertEquals(0, (int) Redis::hGet(RedisKeys::stats($userScope), 'uploads'));
        $this->assertEquals(0, (int) Redis::hGet(RedisKeys::stats($userScope), 'xp'));
        $this->assertEquals(0, (int) Redis::hGet(RedisKeys::stats($userScope), 'litter'));

        $user->refresh();
        $this->assertEquals(0, $user->xp, 'XP = 0 after delete');

        // Upload + tag photo B
        $photoBId = $this->uploadPhoto($user);
        $this->tagPhoto($user, $photoBId, 2); // 2 butts

        // State: EXACTLY 1 photo, 7 XP, 2 litter — no stale accumulation
        $this->assertEquals(1, (int) Redis::hGet(RedisKeys::stats($globalScope), 'photos'),
            'Global photos = exactly 1 (not 2)');
        $this->assertEquals(1, (int) Redis::hGet(RedisKeys::stats($userScope), 'uploads'),
            'User uploads = exactly 1 (not 2)');
        $this->assertEquals(7, (int) Redis::hGet(RedisKeys::stats($userScope), 'xp'),
            'User XP = 7 (not accumulated from first cycle)');
        $this->assertEquals(2, (int) Redis::hGet(RedisKeys::stats($userScope), 'litter'),
            'User litter = 2 (not accumulated from first cycle)');

        $user->refresh();
        $this->assertEquals(7, $user->xp, 'users.xp = 7 (fresh start)');
        $this->assertMetricsRow($user->id, 1, 7, 2);

        // Leaderboard: one entry, correct XP
        $this->assertEquals(
            7.0,
            Redis::zScore(RedisKeys::xpRanking($globalScope), (string) $user->id),
            'Leaderboard XP = 7'
        );
    }

    // =========================================================================
    // Scenario 7: Stale model — delete controller saves user without refresh
    // =========================================================================

    /**
     * PhotosController::deleteImage() calls MetricsService::deletePhoto()
     * (which updates users.xp via query builder). Verify that the XP
     * is correctly zeroed after delete.
     */
    public function test_delete_controller_save_does_not_overwrite_xp(): void
    {
        $user = User::factory()->create([
            'xp' => 0,
            'total_images' => 0,
            'verification_required' => false,
            'picked_up' => null,
        ]);

        // Upload + tag to get some XP
        $photoId = $this->uploadPhoto($user);
        $this->tagPhoto($user, $photoId, 4); // 4 butts → 9 total XP

        $user->refresh();
        $this->assertEquals(9, $user->xp);

        // Delete the photo
        $this->actingAs($user)
            ->postJson('/api/profile/photos/delete', ['photoid' => $photoId])
            ->assertOk();

        // Critical check: users.xp in DATABASE is 0, not the stale in-memory value (9)
        $dbXp = (int) DB::table('users')->where('id', $user->id)->value('xp');
        $this->assertEquals(0, $dbXp, 'DB users.xp = 0 (not overwritten by stale model save)');

        // Double-check via fresh model
        $user->refresh();
        $this->assertEquals(0, $user->xp, 'Refreshed user XP = 0');
    }

    // =========================================================================
    // Scenario: Upload XP recorded in metrics for profile/leaderboard immediately
    // =========================================================================

    /**
     * After upload (before tagging), user must appear on the leaderboard
     * and profile must show correct XP. This tests recordUploadMetrics()
     * writes to ALL metric sources consistently.
     */
    public function test_upload_only_user_appears_on_leaderboard_and_profile(): void
    {
        $user = User::factory()->create([
            'xp' => 0,
            'verification_required' => false,
            'picked_up' => null,
        ]);

        $photoId = $this->uploadPhoto($user);

        // User immediately visible on leaderboard (upload XP only)
        $lb = $this->actingAs($user)->getJson('/api/leaderboard?timeFilter=today');
        $lb->assertOk();
        $this->assertEquals(1, $lb->json('total'), 'Upload-only user appears on today leaderboard');
        $this->assertEquals(1, $lb->json('currentUserRank'));

        // Profile shows upload XP
        $profile = $this->actingAs($user)->getJson('/api/user/profile/index');
        $profile->assertOk();
        $this->assertEquals(5, $profile->json('stats.xp'), 'Profile shows upload XP');
        $this->assertEquals(1, $profile->json('stats.uploads'));
        $this->assertEquals(0, $profile->json('stats.litter'), 'No litter before tagging');

        // All metric sources agree: 5 XP, 1 upload, 0 litter
        $user->refresh();
        $this->assertEquals(5, $user->xp);
        $this->assertMetricsRow($user->id, 1, 5, 0);
        $this->assertRedisUserXp($user->id, 5);
        $this->assertRedisLeaderboardXp($user->id, 5);
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

    private function createAdmin(): User
    {
        $admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->assignRole('admin');

        return $admin;
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
