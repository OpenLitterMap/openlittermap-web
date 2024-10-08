<?php

namespace Tests\Feature\User;

use App\Models\User\User;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    public static function settingsDataProvider(): array
    {
        return [
            'twitter not link' => [['social_twitter' => 'not url'], ['social_twitter']],
            'facebook not link' => [['social_facebook' => 'not url'], ['social_facebook']],
            'instagram not link' => [['social_instagram' => 'not url'], ['social_instagram']],
            'linkedin not link' => [['social_linkedin' => 'not url'], ['social_linkedin']],
            'reddit not link' => [['social_reddit' => 'not url'], ['social_reddit']],
            'personal not link' => [['social_personal' => 'not url'], ['social_personal']],
        ];
    }

    public static function routeDataProvider(): array
    {
        return [
            'web' => ['guard' => 'web', 'route' => '/settings'],
            'api' => ['guard' => 'api', 'route' => '/api/settings'],
        ];
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
        $this->assertEquals('https://twitter.com/user', $user->fresh()->setting('social_twitter'));
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
