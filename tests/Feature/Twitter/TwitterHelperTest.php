<?php

namespace Tests\Feature\Twitter;

use App\Helpers\Twitter;
use Tests\TestCase;

/**
 * Guards the master on/off switch for all X/Twitter posting. Every send method
 * funnels through Twitter::isEnabled(), so these tests lock the kill-switch
 * semantics: off by default, and never live without the flag + production + key.
 */
class TwitterHelperTest extends TestCase
{
    public function test_posting_is_disabled_by_default(): void
    {
        $this->assertFalse((bool) config('services.twitter.enabled'));
        $this->assertFalse(Twitter::isEnabled());
    }

    public function test_disabled_in_production_when_flag_is_off(): void
    {
        $this->app['env'] = 'production';
        config([
            'services.twitter.enabled' => false,
            'services.twitter.consumer_key' => 'a-key',
        ]);

        $this->assertFalse(Twitter::isEnabled());

        $this->app['env'] = 'testing';
    }

    public function test_enabled_only_with_flag_on_in_production_and_full_credentials(): void
    {
        $this->app['env'] = 'production';
        config([
            'services.twitter.enabled' => true,
            'services.twitter.consumer_key' => 'a-key',
            'services.twitter.consumer_secret' => 'a-secret',
            'services.twitter.access_token' => 'a-token',
            'services.twitter.access_secret' => 'a-token-secret',
        ]);

        $this->assertTrue(Twitter::isEnabled());

        $this->app['env'] = 'testing';
    }

    public function test_blank_or_partial_credentials_stay_disabled(): void
    {
        $this->app['env'] = 'production';
        config([
            'services.twitter.enabled' => true,
            'services.twitter.consumer_key' => 'a-key',
            'services.twitter.consumer_secret' => '',     // blank resolves like an empty env var
            'services.twitter.access_token' => null,
            'services.twitter.access_secret' => '',
        ]);

        $this->assertFalse(Twitter::isEnabled());

        $this->app['env'] = 'testing';
    }

    public function test_flag_on_but_missing_key_stays_disabled(): void
    {
        $this->app['env'] = 'production';
        config([
            'services.twitter.enabled' => true,
            'services.twitter.consumer_key' => null,
        ]);

        $this->assertFalse(Twitter::isEnabled());

        $this->app['env'] = 'testing';
    }

    public function test_flag_on_outside_production_stays_disabled(): void
    {
        config([
            'services.twitter.enabled' => true,
            'services.twitter.consumer_key' => 'a-key',
        ]);

        // Environment is 'testing' here — must never post outside production.
        $this->assertFalse(Twitter::isEnabled());
    }
}
