<?php

namespace Tests\Feature\Exports;

use App\Models\Location\Country;
use App\Models\Users\User;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Coverage for `POST /api/download` — the location export endpoint.
 * Auth-only: the previous guest path (with email field) was removed since
 * anonymous CSV exports are an abuse vector against the queue, S3, and
 * outbound mail.
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

    public function test_guest_request_is_rejected_with_401()
    {
        $country = Country::firstOrCreate(['country' => 'AuthLand', 'shortcode' => 'al']);

        $response = $this->postJson('/api/download', [
            'locationType' => 'country',
            'locationId' => $country->id,
        ]);

        $response->assertStatus(401);
    }
}
