<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Posts to Bluesky via the AT Protocol XRPC API. Mirrors the App\Helpers\Twitter
 * shape (post / thread / postWithImage) so App\Helpers\Social can fan out to both.
 *
 * Every network call is wrapped in try/catch + Log — a failure never breaks the
 * calling command. All three send methods funnel through isEnabled().
 */
class Bluesky
{
    /** Bluesky rejects blobs over ~1MB; stay safely under. */
    private const MAX_BLOB_BYTES = 950000;

    /**
     * Master on/off. Requires BLUESKY_ENABLED, the production environment, and an
     * app password. Logs a warning when enabled in production but the password is
     * missing, so a misconfigured deploy is visible rather than silently dead.
     */
    public static function isEnabled(): bool
    {
        if (! config('services.bluesky.enabled') || ! app()->environment('production')) {
            return false;
        }

        if (blank(config('services.bluesky.identifier')) || blank(config('services.bluesky.app_password'))) {
            Log::warning('Bluesky enabled but BLUESKY_IDENTIFIER / BLUESKY_APP_PASSWORD is missing — posting disabled.');

            return false;
        }

        return true;
    }

    public static function post(string $text): void
    {
        if (! self::isEnabled()) {
            return;
        }

        if ($session = self::createSession()) {
            self::createRecord($session, self::record($text));
        }
    }

    /**
     * Post a reply chain. Each record after the first carries reply.root +
     * reply.parent strongRefs taken from the previous createRecord response.
     *
     * @param  string[]  $messages
     * @return array{first_id: string|null, sent: int, total: int}
     */
    public static function thread(array $messages): array
    {
        $result = ['first_id' => null, 'sent' => 0, 'total' => count($messages)];

        if (empty($messages) || ! self::isEnabled()) {
            return $result;
        }

        $session = self::createSession();

        if (! $session) {
            return $result;
        }

        $root = null;
        $parent = null;

        foreach ($messages as $text) {
            $record = self::record($text);

            if ($root && $parent) {
                $record['reply'] = ['root' => $root, 'parent' => $parent];
            }

            $ref = self::createRecord($session, $record);

            if (! $ref) {
                break;
            }

            $root ??= $ref;
            $parent = $ref;
            $result['first_id'] ??= $ref['uri'];
            $result['sent']++;
        }

        return $result;
    }

    public static function postWithImage(string $text, string $imagePath): void
    {
        if (! self::isEnabled()) {
            return;
        }

        $session = self::createSession();

        if (! $session) {
            return;
        }

        $record = self::record($text);

        if ($blob = self::uploadImage($session, $imagePath)) {
            $record['embed'] = [
                '$type' => 'app.bsky.embed.images',
                'images' => [['alt' => '', 'image' => $blob]],
            ];
        }

        self::createRecord($session, $record);
    }

    /**
     * @return array{jwt: string, did: string}|null
     */
    private static function createSession(): ?array
    {
        try {
            $response = Http::post(self::url('com.atproto.server.createSession'), [
                'identifier' => config('services.bluesky.identifier'),
                'password' => config('services.bluesky.app_password'),
            ]);

            if ($response->failed()) {
                Log::error('Bluesky.createSession failed', ['status' => $response->status(), 'body' => $response->body()]);

                return null;
            }

            return ['jwt' => $response->json('accessJwt'), 'did' => $response->json('did')];
        } catch (Throwable $e) {
            Log::error('Bluesky.createSession', [$e->getMessage()]);

            return null;
        }
    }

    /**
     * Post one record; returns its strongRef {uri, cid} or null on failure.
     *
     * @param  array{jwt: string, did: string}  $session
     * @param  array<string, mixed>  $record
     * @return array{uri: string, cid: string}|null
     */
    private static function createRecord(array $session, array $record): ?array
    {
        try {
            $response = Http::withToken($session['jwt'])->post(self::url('com.atproto.repo.createRecord'), [
                'repo' => $session['did'],
                'collection' => 'app.bsky.feed.post',
                'record' => $record,
            ]);

            if ($response->failed()) {
                Log::error('Bluesky.createRecord failed', ['status' => $response->status(), 'body' => $response->body()]);

                return null;
            }

            return ['uri' => $response->json('uri'), 'cid' => $response->json('cid')];
        } catch (Throwable $e) {
            Log::error('Bluesky.createRecord', [$e->getMessage()]);

            return null;
        }
    }

    /**
     * Build a feed-post record with createdAt and link facets.
     *
     * @return array<string, mixed>
     */
    private static function record(string $text): array
    {
        $record = [
            '$type' => 'app.bsky.feed.post',
            'text' => $text,
            'createdAt' => now()->utc()->format('Y-m-d\TH:i:s.v\Z'),
        ];

        if ($facets = self::linkFacets($text)) {
            $record['facets'] = $facets;
        }

        return $record;
    }

    /**
     * Build link facets (UTF-8 byte ranges) for bare URLs so they render
     * clickable — Bluesky does not auto-link plain text. Hashtags are left
     * plain for v1. preg offsets are byte offsets, which is what facets need.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function linkFacets(string $text): array
    {
        if (! preg_match_all('/https?:\/\/[^\s]+/', $text, $matches, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $facets = [];

        foreach ($matches[0] as [$url, $byteOffset]) {
            $url = rtrim($url, '.,;:)]}"\'');

            $facets[] = [
                'index' => ['byteStart' => $byteOffset, 'byteEnd' => $byteOffset + strlen($url)],
                'features' => [['$type' => 'app.bsky.richtext.facet#link', 'uri' => $url]],
            ];
        }

        return $facets;
    }

    /**
     * Recompress the image under the blob limit, then uploadBlob. Returns the
     * blob object for an embed, or null if it can't be uploaded.
     *
     * @param  array{jwt: string, did: string}  $session
     * @return array<string, mixed>|null
     */
    private static function uploadImage(array $session, string $imagePath): ?array
    {
        if (! $imagePath || ! file_exists($imagePath)) {
            return null;
        }

        try {
            $binary = self::compressUnderLimit($imagePath);

            if ($binary === null) {
                Log::error('Bluesky.uploadImage: could not bring image under blob limit', ['path' => $imagePath]);

                return null;
            }

            $response = Http::withToken($session['jwt'])
                ->withBody($binary, 'image/jpeg')
                ->post(self::url('com.atproto.repo.uploadBlob'));

            if ($response->failed()) {
                Log::error('Bluesky.uploadBlob failed', ['status' => $response->status(), 'body' => $response->body()]);

                return null;
            }

            return $response->json('blob');
        } catch (Throwable $e) {
            Log::error('Bluesky.uploadImage', [$e->getMessage()]);

            return null;
        }
    }

    /**
     * Encode as JPEG, stepping quality (and downscaling as a last resort) until
     * under the blob limit. Returns binary, or null if unachievable.
     */
    private static function compressUnderLimit(string $imagePath): ?string
    {
        $image = (new ImageManager(['driver' => 'gd']))->make($imagePath);

        if ($image->width() > 1600) {
            $image->resize(1600, null, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
        }

        foreach ([85, 70, 55, 40] as $quality) {
            $binary = (string) $image->encode('jpg', $quality);

            if (strlen($binary) <= self::MAX_BLOB_BYTES) {
                return $binary;
            }
        }

        $image->resize((int) ($image->width() / 2), null, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });

        $binary = (string) $image->encode('jpg', 40);

        return strlen($binary) <= self::MAX_BLOB_BYTES ? $binary : null;
    }

    private static function url(string $method): string
    {
        return rtrim(config('services.bluesky.service'), '/') . '/xrpc/' . $method;
    }
}
