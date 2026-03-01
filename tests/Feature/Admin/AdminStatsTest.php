<?php

namespace Tests\Feature\Admin;

use App\Enums\VerificationStatus;
use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Photo;
use App\Models\Users\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminStatsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $regularUser;
    protected Country $country;
    protected State $state;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);

        $this->country = Country::factory()->create(['shortcode' => 'IE', 'country' => 'Ireland']);
        $this->state = State::factory()->create([
            'country_id' => $this->country->id,
            'state' => 'Cork',
        ]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->regularUser = User::factory()->create();
    }

    public function test_admin_can_fetch_stats(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/stats');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'stats' => [
                    'queue_total',
                    'queue_today',
                    'by_verification',
                    'by_country',
                    'total_users',
                    'users_today',
                    'flagged_usernames',
                ],
            ]);
    }

    public function test_non_admin_gets_redirected(): void
    {
        $this->actingAs($this->regularUser)
            ->getJson('/api/admin/stats')
            ->assertRedirect('/');
    }

    public function test_unauthenticated_gets_redirected(): void
    {
        $this->getJson('/api/admin/stats')
            ->assertRedirect('/');
    }

    public function test_queue_total_counts_pending_public_photos(): void
    {
        // Pending: public, verified, has summary
        Photo::factory()->count(3)->create([
            'user_id' => $this->regularUser->id,
            'country_id' => $this->country->id,
            'state_id' => $this->state->id,
            'is_public' => true,
            'verified' => VerificationStatus::VERIFIED->value,
            'summary' => json_encode(['smoking' => ['cigarette_butt' => 1]]),
        ]);

        // Not pending: already approved
        Photo::factory()->create([
            'user_id' => $this->regularUser->id,
            'country_id' => $this->country->id,
            'state_id' => $this->state->id,
            'is_public' => true,
            'verified' => VerificationStatus::ADMIN_APPROVED->value,
            'summary' => json_encode(['smoking' => ['cigarette_butt' => 1]]),
        ]);

        // Not pending: private
        Photo::factory()->create([
            'user_id' => $this->regularUser->id,
            'country_id' => $this->country->id,
            'state_id' => $this->state->id,
            'is_public' => false,
            'verified' => VerificationStatus::VERIFIED->value,
            'summary' => json_encode(['smoking' => ['cigarette_butt' => 1]]),
        ]);

        // Not pending: no summary
        Photo::factory()->create([
            'user_id' => $this->regularUser->id,
            'country_id' => $this->country->id,
            'state_id' => $this->state->id,
            'is_public' => true,
            'verified' => VerificationStatus::VERIFIED->value,
            'summary' => null,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/stats');

        $response->assertOk()
            ->assertJsonPath('stats.queue_total', 3);
    }

    public function test_by_country_shows_top_countries(): void
    {
        $germany = Country::factory()->create(['shortcode' => 'DE', 'country' => 'Germany']);

        Photo::factory()->count(5)->create([
            'user_id' => $this->regularUser->id,
            'country_id' => $this->country->id,
            'state_id' => $this->state->id,
            'is_public' => true,
            'verified' => VerificationStatus::VERIFIED->value,
            'summary' => json_encode(['smoking' => ['cigarette_butt' => 1]]),
        ]);

        Photo::factory()->count(2)->create([
            'user_id' => $this->regularUser->id,
            'country_id' => $germany->id,
            'state_id' => $this->state->id,
            'is_public' => true,
            'verified' => VerificationStatus::VERIFIED->value,
            'summary' => json_encode(['smoking' => ['cigarette_butt' => 1]]),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/stats');

        $response->assertOk();

        $byCountry = $response->json('stats.by_country');
        $this->assertEquals(5, $byCountry['Ireland']);
        $this->assertEquals(2, $byCountry['Germany']);
    }

    public function test_by_verification_groups_statuses(): void
    {
        Photo::factory()->count(2)->create([
            'user_id' => $this->regularUser->id,
            'country_id' => $this->country->id,
            'state_id' => $this->state->id,
            'is_public' => true,
            'verified' => VerificationStatus::VERIFIED->value,
            'summary' => json_encode(['smoking' => ['cigarette_butt' => 1]]),
        ]);

        Photo::factory()->create([
            'user_id' => $this->regularUser->id,
            'country_id' => $this->country->id,
            'state_id' => $this->state->id,
            'is_public' => true,
            'verified' => VerificationStatus::ADMIN_APPROVED->value,
            'summary' => json_encode(['smoking' => ['cigarette_butt' => 1]]),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/stats');

        $response->assertOk();

        $byVerification = $response->json('stats.by_verification');
        $this->assertEquals(2, $byVerification['Verified']);
        $this->assertEquals(1, $byVerification['Admin Approved']);
    }

    public function test_flagged_usernames_count(): void
    {
        User::factory()->count(2)->create(['username_flagged' => true]);
        User::factory()->create(['username_flagged' => false]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/stats');

        $response->assertOk()
            ->assertJsonPath('stats.flagged_usernames', 2);
    }

    public function test_total_users_and_users_today(): void
    {
        $totalBefore = User::count();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/stats');

        $response->assertOk();
        $this->assertEquals($totalBefore, $response->json('stats.total_users'));
        // All users in this test were created today (factory)
        $this->assertEquals($totalBefore, $response->json('stats.users_today'));
    }

    public function test_stats_are_cached(): void
    {
        // First request populates cache
        $this->actingAs($this->admin)
            ->getJson('/api/admin/stats')
            ->assertOk();

        $this->assertTrue(Cache::has('admin:dashboard:stats'));

        // Second request uses cache — value should match
        $cached = Cache::get('admin:dashboard:stats');
        $this->assertArrayHasKey('queue_total', $cached);
        $this->assertArrayHasKey('total_users', $cached);
    }
}
