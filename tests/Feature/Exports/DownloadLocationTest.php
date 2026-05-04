<?php

namespace Tests\Feature\Exports;

use App\Models\Location\Country;
use App\Models\Users\User;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Coverage for `POST /api/download` — the public location export endpoint.
 * Guest path is intentional (download.vue exposes an email field for unauthenticated
 * users); throttling + email validation gate it.
 */
class DownloadLocationTest extends TestCase
{
    public function test_authenticated_user_can_export_a_country()
    {
        Storage::fake('s3');
        $user = User::factory()->create();
        $country = Country::firstOrCreate(['country' => 'TestLand', 'shortcode' => 'tl']);

        $response = $this->actingAs($user)->postJson('/api/download', [
            'locationType' => 'country',
            'locationId' => $country->id,
        ]);

        $response->assertOk()->assertJson(['success' => true]);
    }

    public function test_bogus_location_id_returns_location_not_found()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/download', [
            'locationType' => 'city',
            'locationId' => 9999999,
        ]);

        $response->assertOk()->assertJson([
            'success' => false,
            'message' => 'location-not-found',
        ]);
    }

    public function test_guest_without_email_is_rejected()
    {
        $country = Country::firstOrCreate(['country' => 'NoEmailLand', 'shortcode' => 'nl']);

        $response = $this->postJson('/api/download', [
            'locationType' => 'country',
            'locationId' => $country->id,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_guest_with_invalid_email_is_rejected()
    {
        $country = Country::firstOrCreate(['country' => 'BadEmailLand', 'shortcode' => 'bl']);

        $response = $this->postJson('/api/download', [
            'locationType' => 'country',
            'locationId' => $country->id,
            'email' => 'not-an-email',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['email']);
    }
}
