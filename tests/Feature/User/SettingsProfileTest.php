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
    public function items_remaining_key_is_remapped_to_picked_up(): void
    {
        $user = User::factory()->create(['picked_up' => true]);

        $response = $this->actingAs($user)
            ->postJson('/api/settings/update', [
                'key' => 'items_remaining',
                'value' => true,
            ]);

        $response->assertOk();
        // items_remaining=true means picked_up=false (inverted for backward compat)
        // picked_up is not cast to boolean (tri-state: true/false/null), so MySQL returns 0
        $this->assertEquals(0, $user->fresh()->picked_up);
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

    /** @test */
    public function picked_up_can_be_set_directly(): void
    {
        $user = User::factory()->create(['picked_up' => true]);

        $response = $this->actingAs($user)
            ->postJson('/api/settings/update', [
                'key' => 'picked_up',
                'value' => false,
            ]);

        $response->assertOk();
        // picked_up is not cast to boolean (tri-state), so MySQL returns 0
        $this->assertEquals(0, $user->fresh()->picked_up);
    }

    /** @test */
    public function privacy_toggle_maps_name_works(): void
    {
        $user = User::factory()->create(['show_name_maps' => 1]);

        $response = $this->actingAs($user)
            ->postJson('/api/settings/privacy/maps/name');

        $response->assertOk();
        $this->assertEquals(0, $user->fresh()->show_name_maps);
    }

    /** @test */
    public function privacy_toggle_leaderboard_username_works(): void
    {
        $user = User::factory()->create(['show_username' => 1]);

        $response = $this->actingAs($user)
            ->postJson('/api/settings/privacy/leaderboard/username');

        $response->assertOk();
        $this->assertEquals(0, $user->fresh()->show_username);
    }

    /** @test */
    public function toggle_previous_tags_works(): void
    {
        $user = User::factory()->create(['previous_tags' => 0]);

        $response = $this->actingAs($user)
            ->postJson('/api/settings/privacy/toggle-previous-tags');

        $response->assertOk();
        $this->assertEquals(1, $user->fresh()->previous_tags);
    }

    /** @test */
    public function password_change_works(): void
    {
        // Factory mutator double-hashes pre-hashed passwords, so set plain text
        $user = User::factory()->create(['password' => 'password']);

        $response = $this->actingAs($user)
            ->patchJson('/api/settings/details/password', [
                'oldpassword' => 'password',
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ]);

        $response->assertOk();
        $this->assertEquals('success', $response->json('message'));
        $this->assertTrue(\Hash::check('newpassword123', $user->fresh()->password));
    }

    /** @test */
    public function password_change_rejects_wrong_old_password(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $response = $this->actingAs($user)
            ->patchJson('/api/settings/details/password', [
                'oldpassword' => 'wrongpassword',
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ]);

        $response->assertOk();
        $this->assertEquals('fail', $response->json('message'));
    }

    /** @test */
    public function social_links_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->patchJson('/api/settings', [
                'social_twitter' => 'https://twitter.com/testuser',
            ]);

        $response->assertOk();
        $this->assertEquals('https://twitter.com/testuser', $user->fresh()->setting('social_twitter'));
    }

    /** @test */
    public function username_change_flags_for_admin_review(): void
    {
        $user = User::factory()->create(['username_flagged' => false]);

        $response = $this->actingAs($user)
            ->postJson('/api/settings/update', [
                'key' => 'username',
                'value' => 'new-username',
            ]);

        $response->assertOk();
        $this->assertEquals('new-username', $user->fresh()->username);
        $this->assertTrue($user->fresh()->username_flagged);
    }
}
