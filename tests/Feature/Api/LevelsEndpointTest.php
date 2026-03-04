<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class LevelsEndpointTest extends TestCase
{
    public function test_levels_endpoint_returns_thresholds(): void
    {
        $response = $this->getJson('/api/levels');

        $response->assertOk();

        $expected = config('levels.thresholds');
        $response->assertExactJson($expected);
    }

    public function test_levels_endpoint_requires_no_auth(): void
    {
        // No actingAs — unauthenticated request
        $response = $this->getJson('/api/levels');

        $response->assertOk();
        $response->assertJsonFragment(['Noob']);
    }
}
