<?php

namespace Tests\Feature\Auth;

use App\Events\UserSignedUp;
use App\Mail\WelcomeToOpenLitterMap;
use App\Models\Users\User;
use App\Services\UsernameGeneratorService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class UserAccountTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'email' => 'test@example.com',
            'password' => 'secret1234',
        ], $overrides);
    }

    private function registerRoute(): string
    {
        return '/api/auth/register';
    }

    /* ------------------------------------------------------------------
     *  Successful Registration
     * ------------------------------------------------------------------ */

    public function test_user_can_register_with_email_and_password(): void
    {
        Mail::fake();
        Event::fake();

        $response = $this->postJson($this->registerRoute(), $this->validPayload());

        $response->assertStatus(200)
            ->assertJsonStructure(['token', 'user']);

        $user = User::where('email', 'test@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->name);
        $this->assertNotEmpty($user->username);
    }

    public function test_registration_creates_user_with_null_name_and_generated_username(): void
    {
        Mail::fake();
        Event::fake();

        $this->postJson($this->registerRoute(), $this->validPayload());

        $user = User::where('email', 'test@example.com')->first();

        $this->assertNull($user->name);
        $this->assertMatchesRegularExpression('/^[a-z-]+-[a-z-]+-\d{2,4}$/', $user->username);
    }

    public function test_registration_with_optional_username_uses_it(): void
    {
        Mail::fake();
        Event::fake();

        $response = $this->postJson($this->registerRoute(), $this->validPayload([
            'username' => 'my_chosen_name',
        ]));

        $response->assertStatus(200);

        $user = User::where('email', 'test@example.com')->first();

        $this->assertNull($user->name);
        $this->assertEquals('my_chosen_name', $user->username);
    }

    public function test_registration_without_username_generates_one(): void
    {
        Mail::fake();
        Event::fake();

        $response = $this->postJson($this->registerRoute(), $this->validPayload());

        $response->assertStatus(200);

        $user = User::where('email', 'test@example.com')->first();

        $this->assertMatchesRegularExpression('/^[a-z-]+-[a-z-]+-\d{2,4}$/', $user->username);
    }

    public function test_optional_username_must_be_unique(): void
    {
        User::factory()->create(['username' => 'taken_name']);

        $response = $this->postJson($this->registerRoute(), $this->validPayload([
            'username' => 'taken_name',
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors('username');
    }

    public function test_optional_username_must_be_at_least_3_chars(): void
    {
        $response = $this->postJson($this->registerRoute(), $this->validPayload([
            'username' => 'ab',
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors('username');
    }

    public function test_old_mobile_payload_with_name_is_ignored(): void
    {
        Mail::fake();
        Event::fake();

        $response = $this->postJson($this->registerRoute(), $this->validPayload([
            'name' => 'Old Mobile User',
        ]));

        $response->assertStatus(200);

        $user = User::where('email', 'test@example.com')->first();

        $this->assertNull($user->name);
    }

    public function test_password_is_hashed(): void
    {
        Mail::fake();
        Event::fake();

        $this->postJson($this->registerRoute(), $this->validPayload());

        $user = User::where('email', 'test@example.com')->first();

        $this->assertNotNull($user);
        $this->assertNotEquals('secret1234', $user->password);
        $this->assertTrue(\Hash::check('secret1234', $user->password));
    }

    /* ------------------------------------------------------------------
     *  Events & Mail
     * ------------------------------------------------------------------ */

    public function test_registration_fires_registered_event(): void
    {
        Mail::fake();
        Event::fake();

        $this->postJson($this->registerRoute(), $this->validPayload());

        Event::assertDispatched(Registered::class, function ($event) {
            return $event->user->email === 'test@example.com';
        });
    }

    public function test_registration_fires_user_signed_up_event(): void
    {
        Mail::fake();
        Event::fake();

        $this->postJson($this->registerRoute(), $this->validPayload());

        Event::assertDispatched(UserSignedUp::class);
    }

    public function test_registration_sends_welcome_email(): void
    {
        Mail::fake();
        Event::fake();

        $this->postJson($this->registerRoute(), $this->validPayload());

        Mail::assertQueued(WelcomeToOpenLitterMap::class, function ($mail) {
            return $mail->hasTo('test@example.com');
        });
    }

    /* ------------------------------------------------------------------
     *  Email Validation
     * ------------------------------------------------------------------ */

    public function test_email_is_required(): void
    {
        $response = $this->postJson($this->registerRoute(), $this->validPayload(['email' => '']));

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_email_must_be_valid(): void
    {
        $response = $this->postJson($this->registerRoute(), $this->validPayload(['email' => 'not-an-email']));

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_email_maximum_length(): void
    {
        $response = $this->postJson($this->registerRoute(), $this->validPayload([
            'email' => str_repeat('a', 70) . '@test.com',
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_email_must_be_unique(): void
    {
        Mail::fake();
        Event::fake();

        User::factory()->create(['email' => 'test@example.com']);

        $response = $this->postJson($this->registerRoute(), $this->validPayload());

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    /* ------------------------------------------------------------------
     *  Password Validation
     * ------------------------------------------------------------------ */

    public function test_password_is_required(): void
    {
        $response = $this->postJson($this->registerRoute(), $this->validPayload(['password' => '']));

        $response->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_password_minimum_length_is_8(): void
    {
        $response = $this->postJson($this->registerRoute(), $this->validPayload(['password' => 'abcdefg']));

        $response->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    /* ------------------------------------------------------------------
     *  Email Verification
     * ------------------------------------------------------------------ */

    public function test_user_can_verify_email_with_valid_token(): void
    {
        Mail::fake();
        Event::fake();

        $user = User::factory()->create([
            'token' => 'valid-token-123',
            'verified' => 0,
        ]);

        $this->get('/register/confirm/' . $user->token);

        $user->refresh();
        $this->assertEquals(1, $user->verified);
    }

    /* ------------------------------------------------------------------
     *  Edge Cases
     * ------------------------------------------------------------------ */

    public function test_duplicate_email_only_creates_one_user(): void
    {
        Mail::fake();
        Event::fake();

        $this->postJson($this->registerRoute(), $this->validPayload());
        Auth::logout();

        $response = $this->postJson($this->registerRoute(), $this->validPayload([
            'email' => 'test@example.com',
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertEquals(1, User::where('email', 'test@example.com')->count());
    }

    public function test_response_contains_token_and_user(): void
    {
        Mail::fake();
        Event::fake();

        $response = $this->postJson($this->registerRoute(), $this->validPayload());

        $response->assertStatus(200)
            ->assertJsonStructure(['token', 'user'])
            ->assertJsonPath('user.email', 'test@example.com');
    }

    /* ------------------------------------------------------------------
     *  Display Name Accessor
     * ------------------------------------------------------------------ */

    public function test_display_name_returns_name_when_set(): void
    {
        $user = User::factory()->create([
            'name' => 'Sean Lynch',
            'username' => 'mildly-feral-lid-archaeologist-333',
        ]);

        $this->assertEquals('Sean Lynch', $user->display_name);
    }

    public function test_display_name_returns_username_when_name_is_null(): void
    {
        $user = User::factory()->create([
            'name' => null,
            'username' => 'mildly-feral-lid-archaeologist-333',
        ]);

        $this->assertEquals('mildly-feral-lid-archaeologist-333', $user->display_name);
    }
}
