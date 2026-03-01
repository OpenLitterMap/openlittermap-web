<?php

namespace Tests\Feature\Api\Tags;

use App\Models\Photo;
use App\Models\Users\User;
use Database\Seeders\Tags\GenerateTagsSeeder;
use Tests\TestCase;

class MobileTagsCsrfTest extends TestCase
{
    /**
     * Mobile clients send Bearer tokens with no session/cookies.
     * The v3 tag routes must not require CSRF tokens.
     */
    public function test_mobile_bearer_token_can_post_tags_without_csrf(): void
    {
        $this->seed(GenerateTagsSeeder::class);

        $user = User::factory()->create();
        $token = $user->createToken('mobile')->plainTextToken;

        $photo = Photo::factory()->create([
            'user_id' => $user->id,
            'verified' => 0,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v3/tags', [
                'photo_id' => $photo->id,
                'tags' => [],
            ]);

        // Should NOT be 419 (CSRF mismatch). 422 validation error is fine —
        // it proves the request got past CSRF middleware to the controller.
        $this->assertNotEquals(419, $response->status(), 'CSRF should not block Bearer token requests');
    }

    public function test_mobile_bearer_token_can_put_tags_without_csrf(): void
    {
        $this->seed(GenerateTagsSeeder::class);

        $user = User::factory()->create();
        $token = $user->createToken('mobile')->plainTextToken;

        $photo = Photo::factory()->create([
            'user_id' => $user->id,
            'verified' => 0,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v3/tags', [
                'photo_id' => $photo->id,
                'tags' => [],
            ]);

        $this->assertNotEquals(419, $response->status(), 'CSRF should not block Bearer token requests');
    }
}
