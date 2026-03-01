<?php

namespace Tests\Feature\User;

use App\Models\Users\User;
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

    public function test_a_user_can_update_their_settings()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patchJson('/api/settings', [
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
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patchJson('/api/settings', $settings);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors($errors);
    }
}
