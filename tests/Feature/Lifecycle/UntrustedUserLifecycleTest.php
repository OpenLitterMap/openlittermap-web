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
 * Untrusted user lifecycle: the user has verification_required=true.
 *
 * Key difference from trusted: photo stays at verified=0 (UNVERIFIED) after
 * tagging, so it won't appear with actual photo on the map popup. But metrics
 * ARE processed immediately (TagsVerifiedByAdmin fires for all non-school users).
 *
 * Tests the admin approval path: admin promotes verified to ADMIN_APPROVED,
 * which changes map visibility but does NOT re-process metrics (idempotent).
 *
 * No Event::fake(), no MetricsService mocks — tests the real pipeline.
 */
class UntrustedUserLifecycleTest extends TestCase
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

    /**
     * Full untrusted user lifecycle: upload → tag (immediate metrics, no map) →
     * admin approve (map visibility, no duplicate metrics) → delete.
     */
    public function test_untrusted_user_full_lifecycle(): void
    {
        $this->assertNotNull($this->buttsClo, 'CLO for smoking/butts must exist');

        // === STEP 1: Create untrusted user ===
        $user = User::factory()->create([
            'xp' => 0,

            'verification_required' => true, // untrusted
            'picked_up' => null,
        ]);

        $globalScope = RedisKeys::global();
        $userScope = RedisKeys::user($user->id);

        // === STEP 2: Upload photo — same as trusted, 5 XP immediately ===
        $photoId = $this->uploadPhoto($user);
        $photo = Photo::find($photoId);

        $this->assertNotNull($photo);
        $this->assertNull($photo->summary);
        $this->assertEquals(5, (int) $photo->processed_xp, 'Upload-only processed_xp = 5');

        $user->refresh();
        $this->assertEquals(5, $user->xp, 'Upload awards 5 XP');

        // On leaderboard with upload XP
        $this->assertEquals(5.0, Redis::zScore(RedisKeys::xpRanking($globalScope), (string) $user->id));
        $this->assertMetricsRow($user->id, 1, 5, 0);

        // === STEP 3: Tag photo — 3 butts. Metrics processed immediately. ===
        // But photo stays at verified=0 (UNVERIFIED) — not shown on map.
        $this->tagPhoto($user, $photoId, 3);

        $photo->refresh();
        $this->assertEquals(3, $photo->xp, 'Photo XP = tag only (3)');
        $this->assertEquals(8, (int) $photo->processed_xp, 'processed_xp = upload+tag (8)');
        $this->assertNotNull($photo->summary, 'Summary generated');

        // Key difference: verified stays UNVERIFIED (0), NOT ADMIN_APPROVED (2)
        $this->assertEquals(
            VerificationStatus::UNVERIFIED->value,
            $photo->verified->value,
            'Untrusted user photo stays UNVERIFIED after tagging'
        );

        // Despite UNVERIFIED status, metrics ARE processed (immediate leaderboard credit)
        $user->refresh();
        $this->assertEquals(8, $user->xp, 'users.xp = upload(5) + tag(3) = 8');

        $this->assertRedisUserXp($user->id, 8);
        $this->assertRedisLeaderboardXp($user->id, 8);
        $this->assertMetricsRow($user->id, 1, 8, 3);

        // Profile shows full XP and litter
        $profile = $this->actingAs($user)->getJson('/api/user/profile/index');
        $profile->assertOk();
        $this->assertEquals(8, $profile->json('stats.xp'), 'Profile shows full XP');
        $this->assertEquals(3, $profile->json('stats.tags'), 'Profile shows tags');
        $this->assertEquals(1, $profile->json('stats.uploads'));

        // Today leaderboard includes untrusted user
        $lb = $this->actingAs($user)->getJson('/api/leaderboard?timeFilter=today');
        $lb->assertOk();
        $this->assertEquals(1, $lb->json('total'), 'Untrusted user on leaderboard');
        $this->assertEquals(1, $lb->json('currentUserRank'));

        // Location leaderboards also populated
        $countryId = $photo->country_id;
        $this->assertNotFalse(
            Redis::zScore(RedisKeys::xpRanking(RedisKeys::country($countryId)), (string) $user->id),
            'Untrusted user on country leaderboard'
        );

        // === STEP 4: Admin approves photo ===
        // This changes verified=0 → verified=2 (ADMIN_APPROVED).
        // Metrics should NOT double-process (idempotent via fingerprint).
        $admin = $this->createAdmin();

        $approveResponse = $this->actingAs($admin)
            ->postJson('/api/admin/verify', ['photoId' => $photoId]);
        $approveResponse->assertOk();
        $this->assertTrue($approveResponse->json('approved'));

        $photo->refresh();
        $this->assertEquals(
            VerificationStatus::ADMIN_APPROVED->value,
            $photo->verified->value,
            'Photo now ADMIN_APPROVED after admin verify'
        );

        // Metrics unchanged — idempotent (fingerprint + XP match → early return)
        $user->refresh();
        $this->assertEquals(8, $user->xp, 'users.xp unchanged after admin approve');

        $this->assertRedisUserXp($user->id, 8);
        $this->assertRedisLeaderboardXp($user->id, 8);
        $this->assertMetricsRow($user->id, 1, 8, 3);

        // === STEP 5: Delete photo — full reversal ===
        $deleteResponse = $this->actingAs($user)
            ->postJson('/api/profile/photos/delete', ['photoid' => $photoId]);
        $deleteResponse->assertOk();

        $this->assertDatabaseMissing('photos', ['id' => $photoId]);

        // Everything reversed to zero
        $user->refresh();
        $this->assertEquals(0, $user->xp, 'users.xp = 0 after delete');

        $this->assertFalse(
            Redis::zScore(RedisKeys::xpRanking($globalScope), (string) $user->id),
            'Pruned from leaderboard after delete'
        );
        $this->assertEquals(0, (int) Redis::hGet(RedisKeys::stats($userScope), 'xp'));
        $this->assertMetricsRow($user->id, 0, 0, 0);

        // Profile: zeros
        $profile = $this->actingAs($user)->getJson('/api/user/profile/index');
        $this->assertEquals(0, $profile->json('stats.xp'));
        $this->assertEquals(0, $profile->json('stats.tags'));

        // Leaderboard: empty
        $lb = $this->actingAs($user)->getJson('/api/leaderboard?timeFilter=today');
        $this->assertEquals(0, $lb->json('total'));
    }

    /**
     * Verify that admin approval of an already-processed photo is idempotent —
     * TagsVerifiedByAdmin fires again but MetricsService detects matching
     * fingerprint + XP and returns early without double-counting.
     */
    public function test_admin_approve_does_not_double_count_metrics(): void
    {
        $user = User::factory()->create([
            'xp' => 0,
            'verification_required' => true,
            'picked_up' => null,
        ]);

        $photoId = $this->uploadPhoto($user);
        $this->tagPhoto($user, $photoId, 4);

        // Snapshot metrics before admin approve
        $user->refresh();
        $xpBefore = $user->xp;
        $this->assertEquals(9, $xpBefore, 'upload(5) + tag(4) = 9');

        $metricsBefore = $this->getMetricsRow($user->id);

        // Admin approves
        $admin = $this->createAdmin();
        $this->actingAs($admin)
            ->postJson('/api/admin/verify', ['photoId' => $photoId])
            ->assertOk();

        // Verify nothing changed
        $user->refresh();
        $this->assertEquals($xpBefore, $user->xp, 'users.xp unchanged after idempotent approve');

        $metricsAfter = $this->getMetricsRow($user->id);
        $this->assertEquals((int) $metricsBefore->xp, (int) $metricsAfter->xp, 'Metrics XP unchanged');
        $this->assertEquals((int) $metricsBefore->litter, (int) $metricsAfter->litter, 'Metrics litter unchanged');
        $this->assertEquals((int) $metricsBefore->uploads, (int) $metricsAfter->uploads, 'Metrics uploads unchanged');

        $this->assertRedisLeaderboardXp($user->id, $xpBefore);
    }

    /**
     * Untrusted user's photo starts with verified=0.
     * The points API returns it with a placeholder image (not actual photo).
     */
    public function test_unverified_photo_shows_placeholder_on_map(): void
    {
        $user = User::factory()->create([
            'xp' => 0,
            'verification_required' => true,
            'picked_up' => null,
        ]);

        $photoId = $this->uploadPhoto($user);
        $this->tagPhoto($user, $photoId, 2);

        $photo = Photo::find($photoId);
        $this->assertEquals(
            VerificationStatus::UNVERIFIED->value,
            $photo->verified->value,
            'Photo is UNVERIFIED'
        );

        // After admin approval, photo changes to ADMIN_APPROVED
        $admin = $this->createAdmin();
        $this->actingAs($admin)
            ->postJson('/api/admin/verify', ['photoId' => $photoId])
            ->assertOk();

        $photo->refresh();
        $this->assertEquals(
            VerificationStatus::ADMIN_APPROVED->value,
            $photo->verified->value,
            'Photo promoted to ADMIN_APPROVED after admin verify'
        );
    }

    /**
     * Replace tags on an untrusted user's photo — delta should apply correctly.
     */
    public function test_untrusted_user_tag_replacement_adjusts_metrics(): void
    {
        $user = User::factory()->create([
            'xp' => 0,
            'verification_required' => true,
            'picked_up' => null,
        ]);

        $photoId = $this->uploadPhoto($user);
        $this->tagPhoto($user, $photoId, 3);

        // Initial: upload(5) + tag(3) = 8
        $user->refresh();
        $this->assertEquals(8, $user->xp);
        $this->assertMetricsRow($user->id, 1, 8, 3);

        // Increase to 6 butts: upload(5) + tag(6) = 11
        $this->replaceTagsOnPhoto($user, $photoId, 6);

        $user->refresh();
        $this->assertEquals(11, $user->xp, 'XP increased after tag increase');
        $this->assertMetricsRow($user->id, 1, 11, 6);
        $this->assertRedisLeaderboardXp($user->id, 11);

        // Decrease to 1 butt: upload(5) + tag(1) = 6
        $this->replaceTagsOnPhoto($user, $photoId, 1);

        $user->refresh();
        $this->assertEquals(6, $user->xp, 'XP decreased after tag decrease');
        $this->assertMetricsRow($user->id, 1, 6, 1);
        $this->assertRedisLeaderboardXp($user->id, 6);
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
        $row = $this->getMetricsRow($userId);

        $this->assertNotNull($row, 'All-time global per-user metrics row should exist');
        $this->assertEquals($uploads, (int) $row->uploads, 'Metrics uploads');
        $this->assertEquals($xp, (int) $row->xp, 'Metrics XP');
        $this->assertEquals($litter, (int) $row->litter, 'Metrics litter');
    }

    private function getMetricsRow(int $userId): ?object
    {
        return DB::table('metrics')
            ->where('timescale', 0)
            ->where('location_type', LocationType::Global->value)
            ->where('location_id', 0)
            ->where('user_id', $userId)
            ->where('year', 0)
            ->where('month', 0)
            ->first();
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
