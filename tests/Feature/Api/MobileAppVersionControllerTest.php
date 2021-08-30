<?php

namespace Api;

use Tests\TestCase;

class MobileAppVersionControllerTest extends TestCase
{

    public function test_it_returns_mobile_app_versions()
    {
        $response = $this->get('/api/mobile-app-version')
            ->assertOk()
            ->assertJsonStructure([
                'ios' => ['url', 'version'],
                'android' => ['url', 'version']
            ]);

        $this->assertNotEmpty($response['ios']['url']);
        $this->assertNotEmpty($response['ios']['version']);
        $this->assertNotEmpty($response['android']['url']);
        $this->assertNotEmpty($response['android']['version']);
    }
}
