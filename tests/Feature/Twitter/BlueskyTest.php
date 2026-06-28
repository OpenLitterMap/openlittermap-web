<?php

namespace Tests\Feature\Twitter;

use App\Helpers\Bluesky;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BlueskyTest extends TestCase
{
    private function enableBluesky(): void
    {
        $this->app['env'] = 'production';
        config([
            'services.bluesky.enabled' => true,
            'services.bluesky.identifier' => 'olmbot.bsky.social',
            'services.bluesky.app_password' => 'app-pass',
            'services.bluesky.service' => 'https://bsky.social',
        ]);
    }

    protected function tearDown(): void
    {
        $this->app['env'] = 'testing';
        parent::tearDown();
    }

    public function test_posting_is_disabled_by_default(): void
    {
        $this->assertFalse((bool) config('services.bluesky.enabled'));
        $this->assertFalse(Bluesky::isEnabled());
    }

    public function test_post_does_nothing_when_disabled(): void
    {
        Http::fake();

        Bluesky::post('hello');

        Http::assertNothingSent();
    }

    public function test_post_creates_session_then_record(): void
    {
        $this->enableBluesky();

        Http::fake([
            '*com.atproto.server.createSession' => Http::response(['accessJwt' => 'jwt-123', 'did' => 'did:plc:abc']),
            '*com.atproto.repo.createRecord' => Http::response(['uri' => 'at://did/post/1', 'cid' => 'cid1']),
        ]);

        Bluesky::post('Hello Bluesky');

        Http::assertSent(fn ($r) => str_contains($r->url(), 'createSession')
            && $r['identifier'] === 'olmbot.bsky.social'
            && $r['password'] === 'app-pass');

        Http::assertSent(fn ($r) => str_contains($r->url(), 'createRecord')
            && $r['repo'] === 'did:plc:abc'
            && $r['collection'] === 'app.bsky.feed.post'
            && $r['record']['text'] === 'Hello Bluesky'
            && isset($r['record']['createdAt']));
    }

    public function test_thread_chains_reply_refs(): void
    {
        $this->enableBluesky();

        Http::fake([
            '*createSession' => Http::response(['accessJwt' => 'jwt', 'did' => 'did:plc:abc']),
            '*createRecord' => Http::sequence()
                ->push(['uri' => 'at://did/post/1', 'cid' => 'cid1'])
                ->push(['uri' => 'at://did/post/2', 'cid' => 'cid2']),
        ]);

        $result = Bluesky::thread(['First', 'Second']);

        $this->assertEquals(2, $result['sent']);
        $this->assertEquals(2, $result['total']);
        $this->assertEquals('at://did/post/1', $result['first_id']);

        // The reply must point root + parent at the first post.
        Http::assertSent(function ($r) {
            if (! str_contains($r->url(), 'createRecord')) {
                return false;
            }

            $reply = $r['record']['reply'] ?? null;

            return $reply
                && $reply['root']['uri'] === 'at://did/post/1'
                && $reply['parent']['uri'] === 'at://did/post/1';
        });
    }

    public function test_url_gets_a_link_facet_with_byte_range(): void
    {
        $this->enableBluesky();

        Http::fake([
            '*createSession' => Http::response(['accessJwt' => 'jwt', 'did' => 'did:plc:abc']),
            '*createRecord' => Http::response(['uri' => 'at://p', 'cid' => 'c']),
        ]);

        Bluesky::post('See https://openlittermap.com/global now');

        Http::assertSent(function ($r) {
            if (! str_contains($r->url(), 'createRecord')) {
                return false;
            }

            $facet = $r['record']['facets'][0] ?? null;

            return $facet
                && $facet['features'][0]['$type'] === 'app.bsky.richtext.facet#link'
                && $facet['features'][0]['uri'] === 'https://openlittermap.com/global'
                && $facet['index']['byteStart'] === 4
                && $facet['index']['byteEnd'] === 4 + strlen('https://openlittermap.com/global');
        });
    }

    public function test_post_with_image_uploads_blob_then_embeds(): void
    {
        $this->enableBluesky();

        $path = sys_get_temp_dir() . '/olm-bsky-' . uniqid() . '.jpg';
        $image = imagecreatetruecolor(120, 90);
        imagejpeg($image, $path);
        imagedestroy($image);

        Http::fake([
            '*createSession' => Http::response(['accessJwt' => 'jwt', 'did' => 'did:plc:abc']),
            '*uploadBlob' => Http::response(['blob' => ['$type' => 'blob', 'ref' => ['$link' => 'bafy'], 'mimeType' => 'image/jpeg', 'size' => 123]]),
            '*createRecord' => Http::response(['uri' => 'at://p', 'cid' => 'c']),
        ]);

        Bluesky::postWithImage('With image', $path);

        @unlink($path);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'uploadBlob'));
        Http::assertSent(function ($r) {
            if (! str_contains($r->url(), 'createRecord')) {
                return false;
            }

            $embed = $r['record']['embed'] ?? null;

            return $embed
                && $embed['$type'] === 'app.bsky.embed.images'
                && isset($embed['images'][0]['image']);
        });
    }

    public function test_errors_are_swallowed(): void
    {
        $this->enableBluesky();

        Http::fake(['*' => Http::response('nope', 500)]);

        Bluesky::post('hi');                       // must not throw
        $result = Bluesky::thread(['a', 'b']);     // must not throw

        $this->assertEquals(0, $result['sent']);
    }
}
