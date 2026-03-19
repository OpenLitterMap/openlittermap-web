<?php

namespace Tests\Feature\Auth;

use App\Models\Users\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SanctumTokenAuthTest extends TestCase
{
    /* ------------------------------------------------------------------
     *  POST /api/auth/token — Mobile Login
     * ------------------------------------------------------------------ */

    public function test_mobile_login_with_valid_email_returns_token_and_user(): void
    {
        $user = User::factory()->create([
            'email' => 'mobile@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/auth/token', [
            'identifier' => 'mobile@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user'])
            ->assertJsonPath('user.email', 'mobile@example.com');

        $this->assertNotEmpty($response->json('token'));
    }

    public function test_mobile_login_with_valid_username_returns_token(): void
    {
        $user = User::factory()->create([
            'username' => 'mobileuser',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/auth/token', [
            'identifier' => 'mobileuser',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user']);
    }

    public function test_mobile_login_with_legacy_email_field_returns_token(): void
    {
        $user = User::factory()->create([
            'email' => 'legacy@example.com',
            'password' => 'password123',
        ]);

        // Older mobile apps send 'email' instead of 'identifier'
        $response = $this->postJson('/api/auth/token', [
            'email' => 'legacy@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user'])
            ->assertJsonPath('user.email', 'legacy@example.com');
    }

    public function test_mobile_login_with_legacy_username_field_returns_token(): void
    {
        $user = User::factory()->create([
            'username' => 'legacyuser',
            'password' => 'password123',
        ]);

        // Older mobile apps may send 'username' instead of 'identifier'
        $response = $this->postJson('/api/auth/token', [
            'username' => 'legacyuser',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user']);
    }

    public function test_mobile_login_with_invalid_credentials_returns_422(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => 'correct_password',
        ]);

        $response = $this->postJson('/api/auth/token', [
            'identifier' => 'user@example.com',
            'password' => 'wrong_password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('identifier');
    }

    public function test_mobile_login_is_rate_limited(): void
    {
        User::factory()->create([
            'email' => 'rate@example.com',
            'password' => 'password123',
        ]);

        // Exhaust 5 attempts
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/token', [
                'identifier' => 'rate@example.com',
                'password' => 'wrong_password',
            ]);
        }

        // 6th attempt should be throttled
        $response = $this->postJson('/api/auth/token', [
            'identifier' => 'rate@example.com',
            'password' => 'wrong_password',
        ]);

        $response->assertStatus(429);
    }

    /* ------------------------------------------------------------------
     *  Bearer Token — Protected Route Access
     * ------------------------------------------------------------------ */

    public function test_sanctum_token_works_as_bearer_on_protected_route(): void
    {
        $user = User::factory()->create([
            'email' => 'bearer@example.com',
            'password' => 'password123',
        ]);

        // Get a token
        $loginResponse = $this->postJson('/api/auth/token', [
            'identifier' => 'bearer@example.com',
            'password' => 'password123',
        ]);

        $token = $loginResponse->json('token');

        // Use token as Bearer header on a protected route
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/validate-token');

        $response->assertOk()
            ->assertJson(['message' => 'valid']);
    }

    /* ------------------------------------------------------------------
     *  POST /api/register — Registration Returns Token
     * ------------------------------------------------------------------ */

    public function test_register_returns_sanctum_token(): void
    {
        Mail::fake();
        Event::fake();

        $response = $this->postJson('/api/auth/register', [
            'username' => 'newmobile',
            'email' => 'newmobile@example.com',
            'password' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user'])
            ->assertJsonPath('user.email', 'newmobile@example.com');

        // Token works on a protected route
        $token = $response->json('token');

        $protectedResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/validate-token');

        $protectedResponse->assertOk();
    }

    /* ------------------------------------------------------------------
     *  POST /api/validate-token — Sanctum Bearer Validation
     * ------------------------------------------------------------------ */

    public function test_validate_token_works_with_sanctum_bearer(): void
    {
        $user = User::factory()->create();

        $token = $user->createToken('mobile')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/validate-token');

        $response->assertOk()
            ->assertJson(['message' => 'valid']);
    }

    public function test_validate_token_rejects_invalid_token(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer invalid-token-here')
            ->postJson('/api/validate-token');

        $response->assertStatus(401);
    }

    /* ------------------------------------------------------------------
     *  Session Auth — SPA Still Works
     * ------------------------------------------------------------------ */

    public function test_session_auth_still_works_for_spa_routes(): void
    {
        $user = User::factory()->create();

        // actingAs (via Sanctum::actingAs in our TestCase) should work for SPA routes
        $response = $this->actingAs($user)
            ->getJson('/api/user/profile/index');

        $response->assertOk();
    }

    /* ------------------------------------------------------------------
     *  Token Revocation
     * ------------------------------------------------------------------ */

    public function test_login_revokes_previous_mobile_tokens(): void
    {
        $user = User::factory()->create([
            'email' => 'revoke@example.com',
            'password' => 'password123',
        ]);

        // First login — creates a token
        $first = $this->postJson('/api/auth/token', [
            'identifier' => 'revoke@example.com',
            'password' => 'password123',
        ]);

        $firstToken = $first->json('token');
        $this->assertEquals(1, $user->tokens()->count());

        // Second login — should revoke the first token and create a new one
        $second = $this->postJson('/api/auth/token', [
            'identifier' => 'revoke@example.com',
            'password' => 'password123',
        ]);

        $secondToken = $second->json('token');

        // Only one token should exist (the old one was revoked)
        $this->assertEquals(1, $user->tokens()->count());
        $this->assertNotEquals($firstToken, $secondToken);
    }
}
