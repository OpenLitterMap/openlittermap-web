<?php

namespace Tests\Feature\Admin;

use App\Models\Users\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Role::findOrCreate('superadmin', 'web');
        Role::findOrCreate('admin', 'web');
    }

    public function test_user_with_superadmin_role_can_access_admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');

        $this->assertTrue($user->hasRole('superadmin'));

        $this->actingAs($user)
            ->getJson('/api/admin/photos')
            ->assertOk();
    }

    public function test_user_with_admin_role_can_access_admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->assertTrue($user->hasRole('admin'));

        $this->actingAs($user)
            ->getJson('/api/admin/photos')
            ->assertOk();
    }

    public function test_user_without_role_cannot_access_admin(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->hasRole('superadmin'));
        $this->assertFalse($user->hasRole('admin'));

        $this->actingAs($user)
            ->getJson('/api/admin/photos')
            ->assertStatus(302);
    }

    public function test_model_type_matches_user_class(): void
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');

        $this->assertSame(
            get_class($user),
            \DB::table('model_has_roles')->where('model_id', $user->id)->value('model_type')
        );
    }
}
