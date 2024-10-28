<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class InstagramService
{
    protected string $accessToken;
    protected string $instagramAccountId;

    public function __construct ()
    {
        $this->accessToken = env('INSTAGRAM_ACCESS_TOKEN');
        $this->instagramAccountId = env('INSTAGRAM_APP_ID');
    }

    /**
     * Step 1: Create a media container with the image URL and caption.
     */
    public function createMediaContainer ($imageUrl, $caption)
    {
        $endpoint = "https://graph.facebook.com/v17.0/{$this->instagramAccountId}/media";

        $response = Http::post($endpoint, [
            'image_url' => $imageUrl,
            'caption' => $caption,
            'access_token' => $this->accessToken,
        ]);

        return $response->json();
    }

    /**
     * Step 2: Publish the media container as a post on Instagram.
     */
    public function publishMedia ($creationId)
    {
        $endpoint = "https://graph.facebook.com/v17.0/{$this->instagramAccountId}/media_publish";

        $response = Http::post($endpoint, [
            'creation_id' => $creationId,
            'access_token' => $this->accessToken,
        ]);

        return $response->json();
    }

    /**
     * Wrapper function to create and publish a post.
     */
    public function postToInstagram ($imageUrl, $caption)
    {
        // Create media container
        $container = $this->createMediaContainer($imageUrl, $caption);

        if (isset($container['id'])) {
            return $this->publishMedia($container['id']);
        }

        // Return error if container creation fails
        return $container;
    }
}
