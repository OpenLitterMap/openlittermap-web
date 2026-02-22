<?php

namespace Tests\Feature\Auth;

use App\Models\Users\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private string $safeMessage = 'If an account with these details exists, we will send a password reset link.';

    /* ------------------------------------------------------------------
     *  Forgot Password — POST /api/password/email
     * ------------------------------------------------------------------ */

    public function test_reset_link_is_sent_when_using_valid_email(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'person@example.com']);

        $response = $this->postJson('/api/password/email', [
            'login' => 'person@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => $this->safeMessage]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_link_is_sent_when_using_valid_username(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'username' => 'adminguy',
            'email' => 'person@example.com',
        ]);

        $response = $this->postJson('/api/password/email', [
            'login' => 'adminguy',
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => $this->safeMessage]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_unknown_login_returns_same_safe_message(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/password/email', [
            'login' => 'nobody@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => $this->safeMessage]);

        Notification::assertNothingSent();
    }

    public function test_unknown_username_returns_same_safe_message(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/password/email', [
            'login' => 'nonexistentuser',
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => $this->safeMessage]);

        Notification::assertNothingSent();
    }

    public function test_reset_link_requires_login_field(): void
    {
        $response = $this->postJson('/api/password/email', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('login');
    }

    /* ------------------------------------------------------------------
     *  Validate Token — POST /api/password/validate-token
     * ------------------------------------------------------------------ */

    public function test_valid_token_passes_validation(): void
    {
        $user = User::factory()->create(['email' => 'person@example.com']);

        $token = Password::createToken($user);

        $response = $this->postJson('/api/password/validate-token', [
            'token' => $token,
            'email' => 'person@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson(['valid' => true]);
    }

    public function test_invalid_token_fails_validation(): void
    {
        User::factory()->create(['email' => 'person@example.com']);

        $response = $this->postJson('/api/password/validate-token', [
            'token' => 'invalid-token',
            'email' => 'person@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJson(['valid' => false]);
    }

    public function test_expired_token_fails_validation(): void
    {
        $user = User::factory()->create(['email' => 'person@example.com']);

        $token = Password::createToken($user);

        // Consume the token by resetting
        Password::reset(
            ['email' => 'person@example.com', 'password' => 'newpass123', 'password_confirmation' => 'newpass123', 'token' => $token],
            fn ($user, $password) => $user->forceFill(['password' => $password])->save()
        );

        $response = $this->postJson('/api/password/validate-token', [
            'token' => $token,
            'email' => 'person@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJson(['valid' => false]);
    }

    public function test_token_validation_requires_email(): void
    {
        $response = $this->postJson('/api/password/validate-token', [
            'token' => 'some-token',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_token_validation_requires_token(): void
    {
        $response = $this->postJson('/api/password/validate-token', [
            'email' => 'person@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('token');
    }

    /* ------------------------------------------------------------------
     *  Reset Password — POST /api/password/reset
     * ------------------------------------------------------------------ */

    public function test_user_can_reset_password_with_valid_token(): void
    {
        $user = User::factory()->create(['email' => 'person@example.com']);

        $token = Password::createToken($user);

        $response = $this->postJson('/api/password/reset', [
            'token' => $token,
            'email' => 'person@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'user']);

        $user->refresh();
        $this->assertTrue(Hash::check('newpassword123', $user->password));
    }

    public function test_user_is_logged_in_after_reset(): void
    {
        $user = User::factory()->create(['email' => 'person@example.com']);

        $token = Password::createToken($user);

        $this->postJson('/api/password/reset', [
            'token' => $token,
            'email' => 'person@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertStatus(200);

        $this->assertTrue(Auth::check());
        $this->assertEquals($user->id, Auth::id());
    }

    public function test_reset_response_contains_user_data(): void
    {
        $user = User::factory()->create([
            'email' => 'person@example.com',
            'username' => 'adminguy',
        ]);

        $token = Password::createToken($user);

        $response = $this->postJson('/api/password/reset', [
            'token' => $token,
            'email' => 'person@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('user.email', 'person@example.com')
            ->assertJsonPath('user.username', 'adminguy');
    }

    public function test_reset_fails_with_invalid_token(): void
    {
        User::factory()->create(['email' => 'person@example.com']);

        $response = $this->postJson('/api/password/reset', [
            'token' => 'invalid-token',
            'email' => 'person@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertFalse(Auth::check());
    }

    public function test_reset_fails_with_wrong_email(): void
    {
        $user = User::factory()->create(['email' => 'person@example.com']);

        $token = Password::createToken($user);

        $response = $this->postJson('/api/password/reset', [
            'token' => $token,
            'email' => 'wrong@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_reset_fails_when_passwords_dont_match(): void
    {
        $user = User::factory()->create(['email' => 'person@example.com']);

        $token = Password::createToken($user);

        $response = $this->postJson('/api/password/reset', [
            'token' => $token,
            'email' => 'person@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'different456',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_reset_fails_with_short_password(): void
    {
        $user = User::factory()->create(['email' => 'person@example.com']);

        $token = Password::createToken($user);

        $response = $this->postJson('/api/password/reset', [
            'token' => $token,
            'email' => 'person@example.com',
            'password' => 'abcd',
            'password_confirmation' => 'abcd',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_reset_requires_token(): void
    {
        $response = $this->postJson('/api/password/reset', [
            'email' => 'person@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('token');
    }

    public function test_reset_requires_email(): void
    {
        $response = $this->postJson('/api/password/reset', [
            'token' => 'some-token',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_reset_requires_password(): void
    {
        $response = $this->postJson('/api/password/reset', [
            'token' => 'some-token',
            'email' => 'person@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_token_cannot_be_reused(): void
    {
        $user = User::factory()->create(['email' => 'person@example.com']);

        $token = Password::createToken($user);

        // First reset succeeds
        $this->postJson('/api/password/reset', [
            'token' => $token,
            'email' => 'person@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertStatus(200);

        // Logout so guest middleware allows second attempt
        Auth::logout();

        // Same token fails
        $this->postJson('/api/password/reset', [
            'token' => $token,
            'email' => 'person@example.com',
            'password' => 'anotherpassword',
            'password_confirmation' => 'anotherpassword',
        ])->assertStatus(422);
    }

    /* ------------------------------------------------------------------
     *  Full Flow
     * ------------------------------------------------------------------ */

    public function test_full_password_reset_flow_with_email(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'person@example.com',
            'password' => 'oldpassword',
        ]);

        // Step 1: Request reset link via email
        $this->postJson('/api/password/email', [
            'login' => 'person@example.com',
        ])->assertStatus(200);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            // Step 2: Validate the token
            $this->postJson('/api/password/validate-token', [
                'token' => $notification->token,
                'email' => 'person@example.com',
            ])->assertStatus(200)->assertJson(['valid' => true]);

            // Step 3: Use the token to reset
            $response = $this->postJson('/api/password/reset', [
                'token' => $notification->token,
                'email' => 'person@example.com',
                'password' => 'brandnewpass',
                'password_confirmation' => 'brandnewpass',
            ]);

            $response->assertStatus(200)
                ->assertJsonStructure(['message', 'user']);

            // Step 4: Verify new password works and user is logged in
            $user->refresh();
            $this->assertTrue(Hash::check('brandnewpass', $user->password));
            $this->assertFalse(Hash::check('oldpassword', $user->password));
            $this->assertTrue(Auth::check());
            $this->assertEquals($user->id, Auth::id());

            return true;
        });
    }

    public function test_full_password_reset_flow_with_username(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'username' => 'adminguy',
            'email' => 'person@example.com',
            'password' => 'oldpassword',
        ]);

        // Step 1: Request reset link via username
        $this->postJson('/api/password/email', [
            'login' => 'adminguy',
        ])->assertStatus(200);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            // Step 2: Use the token to reset
            $response = $this->postJson('/api/password/reset', [
                'token' => $notification->token,
                'email' => 'person@example.com',
                'password' => 'brandnewpass',
                'password_confirmation' => 'brandnewpass',
            ]);

            $response->assertStatus(200);

            $user->refresh();
            $this->assertTrue(Hash::check('brandnewpass', $user->password));
            $this->assertTrue(Auth::check());

            return true;
        });
    }
}
