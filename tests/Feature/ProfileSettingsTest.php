<?php

namespace Tests\Feature;

use App\Models\Users\User;
use Tests\TestCase;

class ProfileSettingsTest extends TestCase
{
    public function test_profile_index_returns_email_and_privacy_fields(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'show_name' => true,
            'show_username' => false,
            'show_name_maps' => true,
            'show_username_maps' => false,
            'previous_tags' => true,
            'emailsub' => false,
        ]);

        $response = $this->actingAs($user)->getJson('/api/user/profile/index');

        $response->assertOk();
        $response->assertJsonPath('user.email', 'test@example.com');
        $response->assertJsonPath('user.show_name', true);
        $response->assertJsonPath('user.show_username', false);
        $response->assertJsonPath('user.show_name_maps', true);
        $response->assertJsonPath('user.show_username_maps', false);
        $response->assertJsonPath('user.previous_tags', true);
        $response->assertJsonPath('user.emailsub', false);
    }

    public function test_toggle_maps_name_privacy(): void
    {
        $user = User::factory()->create(['show_name_maps' => false]);

        $response = $this->actingAs($user)->postJson('/api/settings/privacy/maps/name');

        $response->assertOk();
        $response->assertJsonPath('show_name_maps', true);
        $this->assertEquals(1, $user->fresh()->show_name_maps);
    }

    public function test_toggle_maps_username_privacy(): void
    {
        $user = User::factory()->create(['show_username_maps' => true]);

        $response = $this->actingAs($user)->postJson('/api/settings/privacy/maps/username');

        $response->assertOk();
        $response->assertJsonPath('show_username_maps', false);
        $this->assertEquals(0, $user->fresh()->show_username_maps);
    }

    public function test_toggle_leaderboard_name_privacy(): void
    {
        $user = User::factory()->create(['show_name' => false]);

        $response = $this->actingAs($user)->postJson('/api/settings/privacy/leaderboard/name');

        $response->assertOk();
        $response->assertJsonPath('show_name', true);
        $this->assertEquals(1, $user->fresh()->show_name);
    }

    public function test_toggle_leaderboard_username_privacy(): void
    {
        $user = User::factory()->create(['show_username' => true]);

        $response = $this->actingAs($user)->postJson('/api/settings/privacy/leaderboard/username');

        $response->assertOk();
        $response->assertJsonPath('show_username', false);
        $this->assertEquals(0, $user->fresh()->show_username);
    }

    public function test_toggle_previous_tags(): void
    {
        $user = User::factory()->create(['previous_tags' => false]);

        $response = $this->actingAs($user)->postJson('/api/settings/privacy/toggle-previous-tags');

        $response->assertOk();
        $response->assertJsonPath('previous_tags', true);
        $this->assertEquals(1, $user->fresh()->previous_tags);
    }

    public function test_update_setting_name(): void
    {
        $user = User::factory()->create(['name' => 'OldName']);

        $response = $this->actingAs($user)->postJson('/api/settings/update', [
            'key' => 'name',
            'value' => 'NewName',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertEquals('NewName', $user->fresh()->name);
    }

    public function test_update_setting_email_unique(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create(['email' => 'me@example.com']);

        $response = $this->actingAs($user)->postJson('/api/settings/update', [
            'key' => 'email',
            'value' => 'taken@example.com',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('msg', 'This email is already taken.');
    }

    public function test_update_setting_rejects_disallowed_key(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/settings/update', [
            'key' => 'password',
            'value' => 'hacked',
        ]);

        $response->assertStatus(422);
    }

    public function test_picked_up_can_be_set_to_true(): void
    {
        $user = User::factory()->create(['picked_up' => null]);

        $response = $this->actingAs($user)->postJson('/api/settings/update', [
            'key' => 'picked_up',
            'value' => true,
        ]);

        $response->assertOk();
        $this->assertEquals(1, $user->fresh()->picked_up);
    }

    public function test_picked_up_can_be_set_to_false(): void
    {
        $user = User::factory()->create(['picked_up' => true]);

        $response = $this->actingAs($user)->postJson('/api/settings/update', [
            'key' => 'picked_up',
            'value' => false,
        ]);

        $response->assertOk();
        $this->assertEquals(0, $user->fresh()->picked_up);
    }

    public function test_picked_up_can_be_set_to_null(): void
    {
        $user = User::factory()->create(['picked_up' => true]);

        $response = $this->actingAs($user)->postJson('/api/settings/update', [
            'key' => 'picked_up',
            'value' => null,
        ]);

        $response->assertOk();
        $this->assertNull($user->fresh()->picked_up);
    }

    public function test_profile_index_returns_picked_up_null(): void
    {
        $user = User::factory()->create(['picked_up' => null]);

        $response = $this->actingAs($user)->getJson('/api/user/profile/index');

        $response->assertOk();
        $this->assertNull($response->json('user.picked_up'));
    }
}
