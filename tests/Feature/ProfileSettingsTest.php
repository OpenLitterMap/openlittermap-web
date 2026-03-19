<?php

namespace Tests\Feature;

use App\Models\Location\Country;
use App\Models\Photo;
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

    // ---------------------------------------------------------------
    // Profile location counts — is_public filtering
    // ---------------------------------------------------------------

    public function test_profile_index_location_counts_include_all_own_photos(): void
    {
        $country = Country::factory()->create();
        $user = User::factory()->create();

        // Public photo
        Photo::factory()->create([
            'user_id' => $user->id,
            'country_id' => $country->id,
            'is_public' => true,
        ]);

        // Private photo (same country)
        Photo::factory()->create([
            'user_id' => $user->id,
            'country_id' => $country->id,
            'is_public' => false,
        ]);

        $response = $this->actingAs($user)->getJson('/api/user/profile/index');

        $response->assertOk();
        // Authenticated user's own profile should count all their photos
        $this->assertEquals(1, $response->json('locations.countries'));
    }

    public function test_public_photos_can_be_set_to_false(): void
    {
        $user = User::factory()->create(['public_photos' => true]);
        $response = $this->actingAs($user)->postJson('/api/settings/update', [
            'key' => 'public_photos',
            'value' => false,
        ]);
        $response->assertOk();
        $this->assertFalse((bool) $user->fresh()->public_photos);
    }

    public function test_public_photos_can_be_set_to_true(): void
    {
        $user = User::factory()->create(['public_photos' => false]);
        $response = $this->actingAs($user)->postJson('/api/settings/update', [
            'key' => 'public_photos',
            'value' => true,
        ]);
        $response->assertOk();
        $this->assertTrue((bool) $user->fresh()->public_photos);
    }

    public function test_profile_index_returns_public_photos_setting(): void
    {
        $user = User::factory()->create(['public_photos' => false]);
        $response = $this->actingAs($user)->getJson('/api/user/profile/index');
        $response->assertOk();
        $response->assertJsonPath('user.public_photos', false);
    }

    public function test_own_geojson_includes_private_photos(): void
    {
        $user = User::factory()->create();
        $country = \App\Models\Location\Country::factory()->create();
        Photo::factory()->create([
            'user_id' => $user->id, 'country_id' => $country->id,
            'is_public' => true, 'verified' => \App\Enums\VerificationStatus::ADMIN_APPROVED->value, 'datetime' => now(),
        ]);
        Photo::factory()->create([
            'user_id' => $user->id, 'country_id' => $country->id,
            'is_public' => false, 'verified' => \App\Enums\VerificationStatus::ADMIN_APPROVED->value, 'datetime' => now(),
        ]);
        $response = $this->actingAs($user)->getJson('/api/user/profile/map?' . http_build_query([
            'period' => 'created_at', 'start' => now()->subDay()->toDateString(), 'end' => now()->addDay()->toDateString(),
        ]));
        $response->assertOk();
        $this->assertCount(2, $response->json('geojson.features'));
    }

    public function test_own_profile_index_location_counts_include_private_photos(): void
    {
        $country1 = \App\Models\Location\Country::factory()->create();
        $country2 = \App\Models\Location\Country::factory()->create();
        $user = User::factory()->create();
        Photo::factory()->create(['user_id' => $user->id, 'country_id' => $country1->id, 'is_public' => true]);
        Photo::factory()->create(['user_id' => $user->id, 'country_id' => $country2->id, 'is_public' => false]);
        $response = $this->actingAs($user)->getJson('/api/user/profile/index');
        $response->assertOk();
        $this->assertEquals(2, $response->json('locations.countries'));
    }

    public function test_public_profile_location_counts_exclude_private_photos(): void
    {
        $country1 = Country::factory()->create();
        $country2 = Country::factory()->create();
        $user = User::factory()->create(['public_profile' => true]);

        // Public photo in country1
        Photo::factory()->create([
            'user_id' => $user->id,
            'country_id' => $country1->id,
            'is_public' => true,
        ]);

        // Private photo in country2 — should be excluded from public profile
        Photo::factory()->create([
            'user_id' => $user->id,
            'country_id' => $country2->id,
            'is_public' => false,
        ]);

        $response = $this->getJson("/api/user/profile/{$user->id}");

        $response->assertOk();
        $this->assertTrue($response->json('public'));
        // Public profile should only count public photos
        $this->assertEquals(1, $response->json('locations.countries'));
    }
}
