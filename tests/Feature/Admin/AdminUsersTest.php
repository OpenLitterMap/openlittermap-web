<?php

namespace Tests\Feature\Admin;

use App\Enums\VerificationStatus;
use App\Models\Photo;
use App\Models\Users\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminUsersTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $userA;
    protected User $userB;
    protected User $userC;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->userA = User::factory()->create([
            'name' => 'Alice Smith',
            'username' => 'alice-smith',
            'email' => 'alice@example.com',
            'xp' => 500,
            'verification_required' => true,
            'created_at' => '2025-01-01',
        ]);

        $this->userB = User::factory()->create([
            'name' => 'Bob Jones',
            'username' => 'bob-jones',
            'email' => 'bob@example.com',
            'xp' => 1200,
            'verification_required' => false,
            'created_at' => '2025-06-01',
        ]);

        $this->userC = User::factory()->create([
            'name' => 'Charlie Brown',
            'username' => 'charlie-brown',
            'email' => 'charlie@example.com',
            'xp' => 100,
            'verification_required' => true,
            'created_at' => '2025-03-01',
        ]);
    }

    // ─── List users ──────────────────────────────────────

    public function test_admin_can_list_users(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/users');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(4, 'users.data'); // admin + 3 users
    }

    public function test_response_includes_expected_fields(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/users');

        $response->assertOk();

        $userData = collect($response->json('users.data'))
            ->firstWhere('id', $this->userA->id);

        $this->assertEquals('Alice Smith', $userData['name']);
        $this->assertEquals('alice-smith', $userData['username']);
        $this->assertEquals('alice@example.com', $userData['email']);
        $this->assertEquals(500, $userData['xp']);
        $this->assertTrue($userData['verification_required']);
        $this->assertFalse($userData['is_trusted']);
        $this->assertArrayHasKey('photos_count', $userData);
        $this->assertArrayHasKey('pending_photos', $userData);
        $this->assertArrayHasKey('roles', $userData);
    }

    public function test_includes_photos_count(): void
    {
        Photo::factory()->count(3)->create(['user_id' => $this->userA->id]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/users');

        $userData = collect($response->json('users.data'))
            ->firstWhere('id', $this->userA->id);

        $this->assertEquals(3, $userData['photos_count']);
    }

    public function test_includes_pending_photos_count(): void
    {
        // 2 pending public photos
        Photo::factory()->count(2)->create([
            'user_id' => $this->userA->id,
            'is_public' => true,
            'verified' => VerificationStatus::VERIFIED->value,
        ]);

        // 1 approved photo — should NOT count as pending
        Photo::factory()->create([
            'user_id' => $this->userA->id,
            'is_public' => true,
            'verified' => VerificationStatus::ADMIN_APPROVED->value,
        ]);

        // 1 private photo — should NOT count as pending
        Photo::factory()->create([
            'user_id' => $this->userA->id,
            'is_public' => false,
            'verified' => VerificationStatus::VERIFIED->value,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/users');

        $userData = collect($response->json('users.data'))
            ->firstWhere('id', $this->userA->id);

        $this->assertEquals(2, $userData['pending_photos']);
    }

    // ─── Search ──────────────────────────────────────────

    public function test_search_by_name(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/users?search=Alice');

        $response->assertOk()
            ->assertJsonCount(1, 'users.data')
            ->assertJsonPath('users.data.0.name', 'Alice Smith');
    }

    public function test_search_by_username(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/users?search=bob-jones');

        $response->assertOk()
            ->assertJsonCount(1, 'users.data')
            ->assertJsonPath('users.data.0.username', 'bob-jones');
    }

    public function test_search_by_email(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/users?search=charlie@example');

        $response->assertOk()
            ->assertJsonCount(1, 'users.data');
    }

    // ─── Trust filter ────────────────────────────────────

    public function test_filter_trusted_users(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/users?trust_filter=trusted');

        $response->assertOk();

        $users = $response->json('users.data');
        foreach ($users as $user) {
            $this->assertTrue($user['is_trusted']);
        }
    }

    public function test_filter_untrusted_users(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/users?trust_filter=untrusted');

        $response->assertOk();

        $users = $response->json('users.data');
        foreach ($users as $user) {
            $this->assertFalse($user['is_trusted']);
        }
    }

    // ─── Sorting ─────────────────────────────────────────

    public function test_sort_by_xp_desc(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/users?sort_by=xp&sort_dir=desc');

        $response->assertOk();

        $xpValues = array_column($response->json('users.data'), 'xp');
        $this->assertEquals($xpValues, collect($xpValues)->sortDesc()->values()->all());
    }

    public function test_sort_by_photos_count(): void
    {
        Photo::factory()->count(5)->create(['user_id' => $this->userC->id]);
        Photo::factory()->count(2)->create(['user_id' => $this->userA->id]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/users?sort_by=photos_count&sort_dir=desc');

        $response->assertOk();

        $counts = array_column($response->json('users.data'), 'photos_count');
        $this->assertEquals($counts, collect($counts)->sortDesc()->values()->all());
    }

    public function test_sort_by_created_at_asc(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/users?sort_by=created_at&sort_dir=asc');

        $response->assertOk();

        $dates = array_column($response->json('users.data'), 'created_at');
        $this->assertEquals($dates, collect($dates)->sort()->values()->all());
    }

    // ─── Pagination ──────────────────────────────────────

    public function test_pagination_works(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/users?per_page=2&page=1');

        $response->assertOk()
            ->assertJsonCount(2, 'users.data')
            ->assertJsonPath('users.total', 4);
    }

    // ─── Auth ────────────────────────────────────────────

    public function test_non_admin_gets_redirected(): void
    {
        $this->actingAs($this->userA)
            ->getJson('/api/admin/users')
            ->assertRedirect('/');
    }

    public function test_unauthenticated_gets_redirected(): void
    {
        $this->getJson('/api/admin/users')
            ->assertRedirect('/');
    }

    // ─── School manager role ─────────────────────────────

    public function test_superadmin_can_grant_school_manager_role(): void
    {
        $superadmin = User::factory()->create();
        $superadmin->assignRole('superadmin');

        $this->assertFalse($this->userA->hasRole('school_manager'));

        $response = $this->actingAs($superadmin)
            ->postJson("/api/admin/users/{$this->userA->id}/school-manager", [
                'enabled' => true,
            ]);

        $response->assertOk()
            ->assertJsonPath('school_manager', true);

        $this->assertTrue($this->userA->fresh()->hasRole('school_manager'));
    }

    public function test_granting_school_manager_sets_remaining_teams(): void
    {
        $superadmin = User::factory()->create();
        $superadmin->assignRole('superadmin');

        $this->userA->update(['remaining_teams' => 0]);

        $this->actingAs($superadmin)
            ->postJson("/api/admin/users/{$this->userA->id}/school-manager", [
                'enabled' => true,
            ])
            ->assertOk();

        $this->assertEquals(1, $this->userA->fresh()->remaining_teams);
    }

    public function test_superadmin_can_revoke_school_manager_role(): void
    {
        $superadmin = User::factory()->create();
        $superadmin->assignRole('superadmin');

        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);
        $this->userA->assignRole('school_manager');

        $response = $this->actingAs($superadmin)
            ->postJson("/api/admin/users/{$this->userA->id}/school-manager", [
                'enabled' => false,
            ]);

        $response->assertOk()
            ->assertJsonPath('school_manager', false);

        $this->assertFalse($this->userA->fresh()->hasRole('school_manager'));
    }

    public function test_non_superadmin_cannot_toggle_school_manager(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/api/admin/users/{$this->userA->id}/school-manager", [
                'enabled' => true,
            ])
            ->assertForbidden();
    }
}
