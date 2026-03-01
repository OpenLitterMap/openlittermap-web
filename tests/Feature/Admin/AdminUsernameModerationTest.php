<?php

namespace Tests\Feature\Admin;

use App\Models\Users\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminUsernameModerationTest extends TestCase
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
            'username' => 'original-username',
            'username_flagged' => false,
        ]);
    }

    // ─── Admin edit username ──────────────────────────────

    public function test_superadmin_can_edit_username(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->patchJson("/api/admin/users/{$this->regularUser->id}/username", [
                'username' => 'new-clean-name',
            ]);

        $response->assertOk()
            ->assertJsonPath('user_id', $this->regularUser->id)
            ->assertJsonPath('username', 'new-clean-name')
            ->assertJsonPath('previous_username', 'original-username');

        $this->assertEquals('new-clean-name', $this->regularUser->fresh()->username);
    }

    public function test_edit_username_clears_flagged(): void
    {
        $this->regularUser->update(['username_flagged' => true]);

        $this->actingAs($this->superadmin)
            ->patchJson("/api/admin/users/{$this->regularUser->id}/username", [
                'username' => 'admin-approved-name',
            ])
            ->assertOk();

        $this->assertFalse($this->regularUser->fresh()->username_flagged);
    }

    public function test_admin_cannot_edit_username(): void
    {
        $this->actingAs($this->admin)
            ->patchJson("/api/admin/users/{$this->regularUser->id}/username", [
                'username' => 'new-name',
            ])
            ->assertForbidden();

        $this->assertEquals('original-username', $this->regularUser->fresh()->username);
    }

    public function test_non_admin_gets_redirected(): void
    {
        $this->actingAs($this->regularUser)
            ->patchJson("/api/admin/users/{$this->regularUser->id}/username", [
                'username' => 'hacked',
            ])
            ->assertRedirect('/');
    }

    public function test_username_must_be_unique(): void
    {
        User::factory()->create(['username' => 'taken-name']);

        $this->actingAs($this->superadmin)
            ->patchJson("/api/admin/users/{$this->regularUser->id}/username", [
                'username' => 'taken-name',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username']);
    }

    public function test_username_validates_min_length(): void
    {
        $this->actingAs($this->superadmin)
            ->patchJson("/api/admin/users/{$this->regularUser->id}/username", [
                'username' => 'ab',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username']);
    }

    public function test_username_validates_max_length(): void
    {
        $this->actingAs($this->superadmin)
            ->patchJson("/api/admin/users/{$this->regularUser->id}/username", [
                'username' => str_repeat('a', 31),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username']);
    }

    public function test_username_rejects_special_characters(): void
    {
        $this->actingAs($this->superadmin)
            ->patchJson("/api/admin/users/{$this->regularUser->id}/username", [
                'username' => 'bad name!',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username']);
    }

    public function test_username_allows_hyphens(): void
    {
        $this->actingAs($this->superadmin)
            ->patchJson("/api/admin/users/{$this->regularUser->id}/username", [
                'username' => 'good-name-123',
            ])
            ->assertOk();

        $this->assertEquals('good-name-123', $this->regularUser->fresh()->username);
    }

    // ─── User self-change flags username ──────────────────

    public function test_user_changing_username_sets_flagged(): void
    {
        $this->actingAs($this->regularUser)
            ->postJson('/api/settings/update', [
                'key' => 'username',
                'value' => 'my-new-name',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $user = $this->regularUser->fresh();
        $this->assertEquals('my-new-name', $user->username);
        $this->assertTrue($user->username_flagged);
    }

    public function test_user_changing_name_does_not_flag(): void
    {
        $this->actingAs($this->regularUser)
            ->postJson('/api/settings/update', [
                'key' => 'name',
                'value' => 'Sean Lynch',
            ])
            ->assertOk();

        $this->assertFalse($this->regularUser->fresh()->username_flagged);
    }

    // ─── Flagged filter in user list ──────────────────────

    public function test_flagged_filter_returns_only_flagged(): void
    {
        $this->regularUser->update(['username_flagged' => true]);
        $unflagged = User::factory()->create(['username_flagged' => false]);

        $response = $this->actingAs($this->superadmin)
            ->getJson('/api/admin/users?flagged=true');

        $response->assertOk();

        $userIds = collect($response->json('users.data'))->pluck('id')->all();
        $this->assertContains($this->regularUser->id, $userIds);
        $this->assertNotContains($unflagged->id, $userIds);
    }

    public function test_no_flagged_filter_returns_all(): void
    {
        $this->regularUser->update(['username_flagged' => true]);

        $response = $this->actingAs($this->superadmin)
            ->getJson('/api/admin/users');

        $response->assertOk();

        // Should contain all users (superadmin, admin, regularUser)
        $userIds = collect($response->json('users.data'))->pluck('id')->all();
        $this->assertContains($this->regularUser->id, $userIds);
        $this->assertContains($this->superadmin->id, $userIds);
    }
}
