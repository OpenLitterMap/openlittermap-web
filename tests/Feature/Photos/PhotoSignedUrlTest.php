<?php

namespace Tests\Feature\Photos;

use App\Models\Photo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhotoSignedUrlTest extends TestCase
{
    use RefreshDatabase;

    private string $endpoint = '/api/photos';

    /** @test */
    public function it_returns_403_without_origin_header()
    {
        $photo = Photo::factory()->create(['is_public' => true, 'verified' => 2]);

        $response = $this->getJson("{$this->endpoint}/{$photo->id}/signed-url");

        $response->assertStatus(403);
    }

    /** @test */
    public function it_returns_403_for_disallowed_origin()
    {
        $photo = Photo::factory()->create(['is_public' => true, 'verified' => 2]);

        $response = $this->getJson(
            "{$this->endpoint}/{$photo->id}/signed-url",
            ['Origin' => 'https://evil-site.com']
        );

        $response->assertStatus(403);
    }

    /** @test */
    public function it_rejects_origin_that_is_a_prefix_match_attack()
    {
        $photo = Photo::factory()->create(['is_public' => true, 'verified' => 2]);

        $response = $this->getJson(
            "{$this->endpoint}/{$photo->id}/signed-url",
            ['Origin' => 'https://openlittermap.com.evil.tld']
        );

        $response->assertStatus(403);
    }

    /** @test */
    public function it_returns_signed_url_for_verified_photo_with_valid_origin()
    {
        Storage::fake('s3');
        $photo = Photo::factory()->create([
            'is_public' => true,
            'verified' => 2,
            'filename' => 'https://olm-public.s3.amazonaws.com/2024/06/15/photo.jpg',
        ]);

        $response = $this->getJson(
            "{$this->endpoint}/{$photo->id}/signed-url",
            ['Origin' => 'https://openlittermap.com']
        );

        $response->assertOk();
        $response->assertJsonStructure(['url', 'expires_in']);
        $this->assertEquals(300, $response->json('expires_in'));

        $url = $response->json('url');
        $this->assertNotNull($url);
        $this->assertNotEmpty($url);
        $this->assertNotEquals('/assets/images/waiting.png', $url);
    }

    /** @test */
    public function it_returns_signed_url_for_unverified_photo()
    {
        $photo = Photo::factory()->create([
            'is_public' => true,
            'verified' => 0,
            'filename' => 'unverified-photo.jpg',
        ]);

        $response = $this->getJson(
            "{$this->endpoint}/{$photo->id}/signed-url",
            ['Origin' => 'https://openlittermap.com']
        );

        $response->assertOk();
        $this->assertNotEquals('/assets/images/waiting.png', $response->json('url'));
        $this->assertStringContainsString('unverified-photo.jpg', $response->json('url'));
    }

    /** @test */
    public function it_returns_404_for_private_photo()
    {
        $photo = Photo::factory()->create(['is_public' => false, 'verified' => 2]);

        $response = $this->getJson(
            "{$this->endpoint}/{$photo->id}/signed-url",
            ['Origin' => 'https://openlittermap.com']
        );

        $response->assertStatus(404);
    }

    /** @test */
    public function it_returns_404_for_nonexistent_photo()
    {
        $response = $this->getJson(
            "{$this->endpoint}/999999/signed-url",
            ['Origin' => 'https://openlittermap.com']
        );

        $response->assertStatus(404);
    }

    /** @test */
    public function it_accepts_www_subdomain_origin()
    {
        $photo = Photo::factory()->create([
            'is_public' => true,
            'verified' => 0,
        ]);

        $response = $this->getJson(
            "{$this->endpoint}/{$photo->id}/signed-url",
            ['Origin' => 'https://www.openlittermap.com']
        );

        $response->assertOk();
    }

    /** @test */
    public function it_accepts_referer_header_when_origin_missing()
    {
        $photo = Photo::factory()->create([
            'is_public' => true,
            'verified' => 0,
        ]);

        $response = $this->getJson(
            "{$this->endpoint}/{$photo->id}/signed-url",
            ['Referer' => 'https://openlittermap.com/map?zoom=17']
        );

        $response->assertOk();
    }

    /** @test */
    public function it_returns_503_when_signed_url_generation_fails()
    {
        // Force production environment so signed URL path is used
        app()->detectEnvironment(fn () => 'production');

        $photo = Photo::factory()->create([
            'is_public' => true,
            'verified' => 2,
            'filename' => 'https://olm-public.s3.amazonaws.com/2024/06/15/photo.jpg',
        ]);

        // Mock Storage to throw
        Storage::shouldReceive('disk')->with('s3')->andReturnSelf();
        Storage::shouldReceive('url')->with('__placeholder__')->andReturn('https://olm-public.s3.amazonaws.com/__placeholder__');
        Storage::shouldReceive('temporaryUrl')->andThrow(new \RuntimeException('S3 error'));

        $response = $this->getJson(
            "{$this->endpoint}/{$photo->id}/signed-url",
            ['Origin' => 'https://openlittermap.com']
        );

        $response->assertStatus(503);
        $this->assertEquals('Image unavailable', $response->json('error'));
    }

    /** @test */
    public function it_returns_production_url_directly_in_local_dev()
    {
        $photo = Photo::factory()->create([
            'is_public' => true,
            'verified' => 2,
            'filename' => 'https://olm-public.s3.amazonaws.com/2024/06/15/photo.jpg',
        ]);

        $response = $this->getJson(
            "{$this->endpoint}/{$photo->id}/signed-url",
            ['Origin' => 'https://olm.test']
        );

        $response->assertOk();
        $this->assertEquals(
            'https://olm-public.s3.amazonaws.com/2024/06/15/photo.jpg',
            $response->json('url')
        );
    }
}
