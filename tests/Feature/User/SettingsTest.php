<?php

namespace Tests\Feature\User;

use Iterator;
use App\Models\User\User;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    public function settingsDataProvider(): Iterator
    {
        yield 'twitter not link' => [['social_twitter' => 'not url'], ['social_twitter']];
        yield 'facebook not link' => [['social_facebook' => 'not url'], ['social_facebook']];
        yield 'instagram not link' => [['social_instagram' => 'not url'], ['social_instagram']];
        yield 'linkedin not link' => [['social_linkedin' => 'not url'], ['social_linkedin']];
        yield 'reddit not link' => [['social_reddit' => 'not url'], ['social_reddit']];
        yield 'personal not link' => [['social_personal' => 'not url'], ['social_personal']];
    }

    public function routeDataProvider(): Iterator
    {
        yield 'web' => ['guard' => 'web', 'route' => '/settings'];
        yield 'api' => ['guard' => 'api', 'route' => '/api/settings'];
    }

    /**
     * @dataProvider routeDataProvider
     */
    public function test_a_user_can_update_their_settings($guard, $route)
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user, $guard)->patchJson($route, [
            'social_twitter' => 'https://twitter.com/user',
            'test setting' => 'this should not be stored',
        ]);

        $response->assertOk();
        $this->assertSame('https://twitter.com/user', $user->fresh()->setting('social_twitter'));
        $this->assertNull($user->fresh()->setting('test setting'));
    }

    /**
     * @dataProvider settingsDataProvider
     */
    public function test_it_validates_settings_updates($settings, $errors)
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patchJson('/settings', $settings);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors($errors);
    }

    /**
     * @dataProvider settingsDataProvider
     */
    public function test_it_validates_settings_updates_from_api($settings, $errors)
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->patchJson('/api/settings', $settings);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors($errors);
    }
}
