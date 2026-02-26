<?php

namespace Tests\Feature\User;

use App\Models\Photo;
use App\Models\Teams\Team;
use App\Models\Users\User;
use App\Services\Redis\RedisKeys;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class DeleteAccountTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Clean up Redis keys that might interfere
        $keys = Redis::keys('{u:*');
        if ($keys && is_array($keys)) {
            Redis::del($keys);
        }

        $globalKeys = Redis::keys('{g}:*');
        if ($globalKeys && is_array($globalKeys)) {
            Redis::del($globalKeys);
        }
    }

    /** @test */
    public function full_account_deletion_preserves_photos_as_anonymous(): void
    {
        $user = User::factory()->create(['password' => 'secret123']);
        $photo = Photo::factory()->for($user)->create([
            'verified' => 2,
            'lat' => 51.5074,
            'lon' => -0.1278,
        ]);

        $photoId = $photo->id;
        $userId = $user->id;

        $response = $this->actingAs($user)
            ->postJson('/api/settings/delete-account', [
                'password' => 'secret123',
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);

        // User is hard-deleted
        $this->assertDatabaseMissing('users', ['id' => $userId]);

        // Photo is preserved with null user_id (anonymous)
        $this->assertDatabaseHas('photos', ['id' => $photoId]);

        $anonymousPhoto = Photo::withoutGlobalScopes()->find($photoId);
        $this->assertNull($anonymousPhoto->user_id);
    }

    /** @test */
    public function deletion_nullifies_verified_by_references(): void
    {
        $verifier = User::factory()->create(['password' => 'secret123']);
        $uploader = User::factory()->create();

        $photo = Photo::factory()->for($uploader)->create([
            'verified' => 2,
            'verified_by' => $verifier->id,
            'lat' => 51.5074,
            'lon' => -0.1278,
        ]);

        $verifierId = $verifier->id;

        $response = $this->actingAs($verifier)
            ->postJson('/api/settings/delete-account', [
                'password' => 'secret123',
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);

        // Photo's verified_by is nullified (SET NULL on delete)
        $photo->refresh();
        $this->assertNull($photo->verified_by);
    }

    /** @test */
    public function deletion_detaches_photos_from_user_teams_before_deleting_teams(): void
    {
        $user = User::factory()->create(['password' => 'secret123']);
        $team = Team::factory()->create([
            'leader' => $user->id,
            'created_by' => $user->id,
        ]);

        // Another user's photo in this team
        $otherUser = User::factory()->create();
        $teamPhoto = Photo::factory()->for($otherUser)->create([
            'verified' => 2,
            'team_id' => $team->id,
            'lat' => 51.5074,
            'lon' => -0.1278,
        ]);

        $teamPhotoId = $teamPhoto->id;
        $teamId = $team->id;

        $response = $this->actingAs($user)
            ->postJson('/api/settings/delete-account', [
                'password' => 'secret123',
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);

        // Team is deleted
        $this->assertDatabaseMissing('teams', ['id' => $teamId]);

        // Other user's photo is preserved with null team_id
        $this->assertDatabaseHas('photos', ['id' => $teamPhotoId]);
        $teamPhoto->refresh();
        $this->assertNull($teamPhoto->team_id);
    }

    /** @test */
    public function deletion_cleans_up_per_user_metrics_rows(): void
    {
        $user = User::factory()->create(['password' => 'secret123']);

        // Seed some per-user metrics rows
        DB::table('metrics')->insert([
            'timescale' => 3,
            'location_type' => 0,
            'location_id' => 0,
            'user_id' => $user->id,
            'bucket_date' => '2026-02-01',
            'year' => 2026,
            'month' => 2,
            'week' => 5,
            'xp' => 100,
            'uploads' => 5,
            'tags' => 10,
            'litter' => 8,
        ]);

        $this->assertDatabaseHas('metrics', ['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->postJson('/api/settings/delete-account', [
                'password' => 'secret123',
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);

        // Per-user metrics rows are deleted
        $this->assertDatabaseMissing('metrics', ['user_id' => $user->id]);
    }

    /** @test */
    public function redis_cleanup_removes_user_keys_and_rankings(): void
    {
        $user = User::factory()->create(['password' => 'secret123']);
        $photo = Photo::factory()->for($user)->create([
            'verified' => 2,
            'lat' => 51.5074,
            'lon' => -0.1278,
        ]);

        $userId = $user->id;
        $userScope = RedisKeys::user($userId);

        // Seed Redis data for this user
        Redis::hSet(RedisKeys::stats($userScope), 'uploads', 5);
        Redis::hSet(RedisKeys::stats($userScope), 'xp', 100);
        Redis::hSet("{$userScope}:tags", 'obj:1', 3);
        Redis::setBit(RedisKeys::userBitmap($userId), 1, true);
        Redis::zAdd(RedisKeys::xpRanking(RedisKeys::global()), 100, (string) $userId);
        Redis::zAdd(RedisKeys::contributorRanking(RedisKeys::global()), 5, (string) $userId);

        // Verify data was seeded
        $this->assertEquals('5', Redis::hGet(RedisKeys::stats($userScope), 'uploads'));
        $this->assertNotFalse(Redis::zScore(RedisKeys::xpRanking(RedisKeys::global()), (string) $userId));

        $response = $this->actingAs($user)
            ->postJson('/api/settings/delete-account', [
                'password' => 'secret123',
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);

        // Verify Redis keys are cleaned up
        $this->assertEmpty(Redis::hGetAll(RedisKeys::stats($userScope)));
        $this->assertEmpty(Redis::hGetAll("{$userScope}:tags"));
        $this->assertFalse(Redis::zScore(RedisKeys::xpRanking(RedisKeys::global()), (string) $userId));
        $this->assertFalse(Redis::zScore(RedisKeys::contributorRanking(RedisKeys::global()), (string) $userId));
    }

    /** @test */
    public function redis_cleanup_removes_from_location_scoped_rankings(): void
    {
        $user = User::factory()->create(['password' => 'secret123']);
        $photo = Photo::factory()->for($user)->create([
            'verified' => 2,
            'lat' => 51.5074,
            'lon' => -0.1278,
        ]);

        $userId = $user->id;
        $countryScope = RedisKeys::country($photo->country_id);

        // Seed location-scoped ranking
        Redis::zAdd(RedisKeys::xpRanking($countryScope), 50, (string) $userId);
        Redis::zAdd(RedisKeys::contributorRanking($countryScope), 3, (string) $userId);

        $this->assertNotFalse(Redis::zScore(RedisKeys::xpRanking($countryScope), (string) $userId));

        $response = $this->actingAs($user)
            ->postJson('/api/settings/delete-account', [
                'password' => 'secret123',
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $this->assertFalse(Redis::zScore(RedisKeys::xpRanking($countryScope), (string) $userId));
        $this->assertFalse(Redis::zScore(RedisKeys::contributorRanking($countryScope), (string) $userId));
    }

    /** @test */
    public function delete_account_rejects_wrong_password(): void
    {
        $user = User::factory()->create(['password' => 'secret123']);

        $response = $this->actingAs($user)
            ->postJson('/api/settings/delete-account', [
                'password' => 'wrong-password',
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('msg', 'password does not match');

        // User should still exist
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    /** @test */
    public function delete_account_requires_authentication(): void
    {
        $response = $this->postJson('/api/settings/delete-account', [
            'password' => 'anything',
        ]);

        $response->assertUnauthorized();
    }
}
