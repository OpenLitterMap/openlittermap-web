<?php

namespace Tests\Feature\User;

use App\Models\Photo;
use App\Models\Users\User;
use Tests\TestCase;

class ProfileGeojsonTest extends TestCase
{
    /** @test */
    public function geojson_returns_admin_approved_photos(): void
    {
        $user = User::factory()->create();

        // Verified at level 2 (ADMIN_APPROVED) — should be included
        $approved = Photo::factory()->for($user)->create([
            'verified' => 2,
            'datetime' => '2026-01-15 10:00:00',
            'summary' => ['smoking' => ['butts' => 1]],
        ]);

        // Verified at level 3 (BBOX_APPLIED) — should also be included (>= 2)
        $bbox = Photo::factory()->for($user)->create([
            'verified' => 3,
            'datetime' => '2026-01-16 10:00:00',
            'summary' => ['food' => ['wrapper' => 1]],
        ]);

        // Unverified (level 0) — should NOT be included
        $unverified = Photo::factory()->for($user)->create([
            'verified' => 0,
            'datetime' => '2026-01-17 10:00:00',
        ]);

        // Just tagged, not yet approved (level 1) — should NOT be included
        $tagged = Photo::factory()->for($user)->create([
            'verified' => 1,
            'datetime' => '2026-01-18 10:00:00',
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/user/profile/map?' . http_build_query([
                'period' => 'datetime',
                'start' => '2026-01-01',
                'end' => '2026-12-31',
            ]));

        $response->assertOk();
        $features = $response->json('geojson.features');

        // Should contain only the 2 photos with verified >= 2
        $this->assertCount(2, $features);

        $photoIds = collect($features)->pluck('properties.photo_id')->all();
        $this->assertContains($approved->id, $photoIds);
        $this->assertContains($bbox->id, $photoIds);
        $this->assertNotContains($unverified->id, $photoIds);
        $this->assertNotContains($tagged->id, $photoIds);

        // Verify response uses 'summary' key, not 'result_string'
        $firstProps = $features[0]['properties'];
        $this->assertArrayHasKey('summary', $firstProps);
        $this->assertArrayNotHasKey('result_string', $firstProps);
        $this->assertIsArray($firstProps['summary']);
    }
}
