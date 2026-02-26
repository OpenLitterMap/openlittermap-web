<?php

namespace Tests\Feature\User;

use App\Models\Users\User;
use Tests\TestCase;

class SettingsProfileTest extends TestCase
{
    /** @test */
    public function mass_assignment_blocked_for_disallowed_keys(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/settings/update', [
                'key' => 'is_admin',
                'value' => true,
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
    }

    /** @test */
    public function mass_assignment_blocked_for_verification_required(): void
    {
        $user = User::factory()->create(['verification_required' => true]);

        $response = $this->actingAs($user)
            ->postJson('/api/settings/update', [
                'key' => 'verification_required',
                'value' => false,
            ]);

        $response->assertStatus(422);
        $this->assertTrue($user->fresh()->verification_required);
    }

    /** @test */
    public function allowed_setting_name_can_be_updated(): void
    {
        $user = User::factory()->create(['name' => 'Old Name']);

        $response = $this->actingAs($user)
            ->postJson('/api/settings/update', [
                'key' => 'name',
                'value' => 'New Name',
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertEquals('New Name', $user->fresh()->name);
    }

    /** @test */
    public function allowed_setting_emailsub_can_be_toggled(): void
    {
        $user = User::factory()->create(['emailsub' => 1]);

        $response = $this->actingAs($user)
            ->postJson('/api/settings/update', [
                'key' => 'emailsub',
                'value' => false,
            ]);

        $response->assertOk();
        $this->assertEquals(0, $user->fresh()->emailsub);
    }

    /** @test */
    public function picked_up_key_is_remapped_to_items_remaining(): void
    {
        $user = User::factory()->create(['items_remaining' => 0]);

        $response = $this->actingAs($user)
            ->postJson('/api/settings/update', [
                'key' => 'picked_up',
                'value' => false,
            ]);

        $response->assertOk();
        // picked_up=false means items_remaining=true (inverted)
        $this->assertEquals(1, $user->fresh()->items_remaining);
    }

    /** @test */
    public function public_profile_can_be_toggled(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/settings/update', [
                'key' => 'public_profile',
                'value' => true,
            ]);

        $response->assertOk();
        $this->assertTrue($user->fresh()->public_profile);
    }

    /** @test */
    public function old_delete_route_returns_404(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/settings/delete', [
                'password' => 'password',
            ]);

        // Route no longer exists — should 404 or 405
        $this->assertTrue(in_array($response->status(), [404, 405]));
    }

    /** @test */
    public function old_security_route_returns_404(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/settings/security', [
                'first_name' => true,
            ]);

        $this->assertTrue(in_array($response->status(), [404, 405]));
    }

    /** @test */
    public function update_validates_value_type(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/settings/update', [
                'key' => 'name',
                'value' => 'ab', // min:3
            ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function update_rejects_duplicate_email(): void
    {
        $existing = User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/settings/update', [
                'key' => 'email',
                'value' => 'taken@example.com',
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('msg', 'This email is already taken.');
    }
}
