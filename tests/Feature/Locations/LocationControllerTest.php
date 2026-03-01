<?php

namespace Tests\Feature\Locations;

use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Photo;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LocationControllerTest extends TestCase
{
    /**
     * Regression test: GET /api/v1/locations returned countries: 0
     * because of a manual_verify filter that excluded all countries.
     */
    public function test_locations_index_returns_countries_with_metrics()
    {
        $country = Country::factory()->create();
        $state = State::factory()->create(['country_id' => $country->id]);

        Photo::factory()->create([
            'country_id' => $country->id,
            'state_id' => $state->id,
            'is_public' => true,
        ]);

        // Insert all-time metrics for this country (timescale 0, location_type 1 = country)
        DB::table('metrics')->insert([
            'timescale' => 0,
            'location_type' => 1,
            'location_id' => $country->id,
            'user_id' => 0,
            'bucket_date' => '1970-01-01',
            'year' => 0,
            'month' => 0,
            'week' => 0,
            'uploads' => 1,
            'tags' => 5,
            'litter' => 5,
            'xp' => 5,
        ]);

        $response = $this->getJson('/api/v1/locations');

        $response->assertOk();
        $response->assertJsonPath('location_type', 'country');
        $response->assertJsonPath('stats.countries', 1);

        $locations = $response->json('locations');
        $this->assertCount(1, $locations);
        $this->assertEquals($country->id, $locations[0]['id']);
        $this->assertGreaterThan(0, $locations[0]['total_tags']);
    }

    public function test_locations_index_returns_correct_structure()
    {
        $response = $this->getJson('/api/v1/locations');

        $response->assertOk();
        $response->assertJsonStructure([
            'stats' => ['photos', 'tags', 'countries'],
            'activity',
            'locations',
            'location_type',
            'breadcrumbs',
        ]);
        $response->assertJsonPath('location_type', 'country');
    }
}
