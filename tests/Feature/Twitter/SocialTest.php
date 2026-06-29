<?php

namespace Tests\Feature\Twitter;

use App\Helpers\Social;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SocialTest extends TestCase
{
    private function enableBlueskyOnly(): void
    {
        $this->app['env'] = 'production';
        config([
            'services.bluesky.enabled' => true,
            'services.bluesky.identifier' => 'olmbot.bsky.social',
            'services.bluesky.app_password' => 'pw',
            'services.bluesky.service' => 'https://bsky.social',
            'services.twitter.enabled' => false,   // X stays gated off
        ]);
    }

    protected function tearDown(): void
    {
        $this->app['env'] = 'testing';
        parent::tearDown();
    }

    public function test_thread_returns_zero_when_no_network_enabled(): void
    {
        // testing env → neither network enabled; command's `sent === 0` → SUCCESS path
        $result = Social::thread(['a', 'b']);

        $this->assertEquals(0, $result['sent']);
        $this->assertEquals(0, $result['total']);
        $this->assertNull($result['first_id']);
    }

    public function test_thread_counts_only_enabled_networks(): void
    {
        $this->enableBlueskyOnly();

        Http::fake([
            '*createSession' => Http::response(['accessJwt' => 'jwt', 'did' => 'did:plc:abc']),
            '*createRecord' => Http::sequence()
                ->push(['uri' => 'at://1', 'cid' => 'c1'])
                ->push(['uri' => 'at://2', 'cid' => 'c2']),
        ]);

        $result = Social::thread(['one', 'two']);

        // Only Bluesky enabled → total = 2 (not 4 across two networks), sent = 2.
        $this->assertEquals(2, $result['sent']);
        $this->assertEquals(2, $result['total']);
        $this->assertEquals('at://1', $result['first_id']);
    }

    public function test_text_fans_out_to_enabled_network(): void
    {
        $this->enableBlueskyOnly();

        Http::fake([
            '*createSession' => Http::response(['accessJwt' => 'jwt', 'did' => 'did:plc:abc']),
            '*createRecord' => Http::response(['uri' => 'at://1', 'cid' => 'c1']),
        ]);

        Social::text('Hello world');

        Http::assertSent(fn ($r) => str_contains($r->url(), 'createRecord')
            && $r['record']['text'] === 'Hello world');
    }
}
