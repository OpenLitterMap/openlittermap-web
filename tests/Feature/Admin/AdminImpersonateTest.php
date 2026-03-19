<?php

namespace Tests\Feature\Admin;

use App\Models\Users\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminImpersonateTest extends TestCase
{
    use RefreshDatabase;

    protected User $superadmin;
    protected User $admin;
    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);

        $this->superadmin = User::factory()->create();
        $this->superadmin->assignRole('superadmin');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->regularUser = User::factory()->create([
            'username' => 'test-user',
        ]);
    }

    // ─── Start impersonation ─────────────────────────────

    public function test_superadmin_can_impersonate_regular_user(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->postJson("/api/admin/users/{$this->regularUser->id}/impersonate");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user_id', $this->regularUser->id)
            ->assertJsonPath('username', 'test-user');
    }

    public function test_impersonation_switches_auth_session(): void
    {
        $this->actingAs($this->superadmin)
            ->postJson("/api/admin/users/{$this->regularUser->id}/impersonate")
            ->assertOk();

        $this->assertAuthenticatedAs($this->regularUser);
    }

    public function test_impersonation_stores_original_admin_in_session(): void
    {
        $this->actingAs($this->superadmin)
            ->withSession([])
            ->postJson("/api/admin/users/{$this->regularUser->id}/impersonate")
            ->assertOk();

        $this->assertEquals($this->superadmin->id, session('impersonating_from'));
    }

    public function test_cannot_impersonate_admin_user(): void
    {
        $this->actingAs($this->superadmin)
            ->postJson("/api/admin/users/{$this->admin->id}/impersonate")
            ->assertForbidden()
            ->assertJsonPath('message', 'Cannot impersonate admin users.');
    }

    public function test_cannot_impersonate_superadmin_user(): void
    {
        $otherSuperadmin = User::factory()->create();
        $otherSuperadmin->assignRole('superadmin');

        $this->actingAs($this->superadmin)
            ->postJson("/api/admin/users/{$otherSuperadmin->id}/impersonate")
            ->assertForbidden()
            ->assertJsonPath('message', 'Cannot impersonate admin users.');
    }

    public function test_cannot_impersonate_yourself(): void
    {
        $this->actingAs($this->superadmin)
            ->postJson("/api/admin/users/{$this->superadmin->id}/impersonate")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Cannot impersonate yourself.');
    }

    public function test_non_superadmin_cannot_impersonate(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/api/admin/users/{$this->regularUser->id}/impersonate")
            ->assertForbidden();
    }

    public function test_unauthenticated_cannot_impersonate(): void
    {
        $this->postJson("/api/admin/users/{$this->regularUser->id}/impersonate")
            ->assertRedirect('/');
    }

    // ─── Stop impersonation ──────────────────────────────

    public function test_can_stop_impersonation(): void
    {
        // Start impersonating
        $this->actingAs($this->superadmin)
            ->withSession([])
            ->postJson("/api/admin/users/{$this->regularUser->id}/impersonate")
            ->assertOk();

        // Now stop — we're the regular user with impersonating_from in session
        $response = $this->postJson('/api/impersonate/stop');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user_id', $this->superadmin->id);

        $this->assertAuthenticatedAs($this->superadmin);
    }

    public function test_stop_clears_session_key(): void
    {
        $this->actingAs($this->superadmin)
            ->withSession([])
            ->postJson("/api/admin/users/{$this->regularUser->id}/impersonate")
            ->assertOk();

        $this->postJson('/api/impersonate/stop')->assertOk();

        $this->assertNull(session('impersonating_from'));
    }

    public function test_stop_fails_when_not_impersonating(): void
    {
        $this->actingAs($this->regularUser)
            ->postJson('/api/impersonate/stop')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Not currently impersonating.');
    }
}
