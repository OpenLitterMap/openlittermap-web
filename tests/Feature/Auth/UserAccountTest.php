<?php

namespace Tests\Feature\Auth;

use App\Events\UserSignedUp;
use App\Mail\NewUserRegMail;
use App\Models\Users\User;
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
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'secret123',
        ], $overrides);
    }

    /**
     * Both web and API registration hit the same controller.
     * Web:  POST /register
     * API:  POST /api/auth/register
     */
    private function registerRoute(): string
    {
        return '/api/auth/register';
    }

    /* ------------------------------------------------------------------
     *  Successful Registration
     * ------------------------------------------------------------------ */

    public function test_user_can_register_with_valid_data(): void
    {
        Mail::fake();
        Event::fake();

        $response = $this->postJson($this->registerRoute(), $this->validPayload());

        $response->assertStatus(200)
            ->assertJsonStructure(['user_id', 'email']);

        $this->assertDatabaseHas('users', [
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@example.com',
        ]);
    }

    public function test_user_can_register_without_name(): void
    {
        Mail::fake();
        Event::fake();

        $response = $this->postJson($this->registerRoute(), $this->validPayload(['name' => null]));

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'username' => 'testuser',
            'email' => 'test@example.com',
            'name' => '',
        ]);
    }

    public function test_registered_user_has_correct_initial_limits(): void
    {
        Mail::fake();
        Event::fake();

        $this->postJson($this->registerRoute(), $this->validPayload());

        $user = User::where('email', 'test@example.com')->first();

        $this->assertNotNull($user);
        $this->assertEquals(1000, $user->images_remaining);
        $this->assertEquals(5000, $user->verify_remaining);
    }

    public function test_password_is_hashed(): void
    {
        Mail::fake();
        Event::fake();

        $this->postJson($this->registerRoute(), $this->validPayload());

        $user = User::where('email', 'test@example.com')->first();

        $this->assertNotNull($user);
        $this->assertNotEquals('secret123', $user->password);
        $this->assertTrue(\Hash::check('secret123', $user->password));
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

        Mail::assertSent(NewUserRegMail::class, function ($mail) {
            return $mail->hasTo('test@example.com');
        });
    }

    /* ------------------------------------------------------------------
     *  Username Validation
     * ------------------------------------------------------------------ */

    public function test_username_is_required(): void
    {
        $response = $this->postJson($this->registerRoute(), $this->validPayload(['username' => '']));

        $response->assertStatus(422)
            ->assertJsonValidationErrors('username');
    }

    public function test_username_minimum_length(): void
    {
        $response = $this->postJson($this->registerRoute(), $this->validPayload(['username' => 'ab']));

        $response->assertStatus(422)
            ->assertJsonValidationErrors('username');
    }

    public function test_username_maximum_length(): void
    {
        $response = $this->postJson($this->registerRoute(), $this->validPayload([
            'username' => str_repeat('a', 21),
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors('username');
    }

    public function test_username_must_be_unique(): void
    {
        Mail::fake();
        Event::fake();

        User::factory()->create(['username' => 'testuser']);

        $response = $this->postJson($this->registerRoute(), $this->validPayload());

        $response->assertStatus(422)
            ->assertJsonValidationErrors('username');
    }

    public function test_username_cannot_match_password(): void
    {
        $response = $this->postJson($this->registerRoute(), $this->validPayload([
            'username' => 'secret123',
            'password' => 'secret123',
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors('username');
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

    public function test_password_minimum_length(): void
    {
        $response = $this->postJson($this->registerRoute(), $this->validPayload(['password' => 'abcd']));

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
            'username' => 'different',
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertEquals(1, User::where('email', 'test@example.com')->count());
    }

    public function test_duplicate_username_is_rejected(): void
    {
        Mail::fake();
        Event::fake();

        $this->postJson($this->registerRoute(), $this->validPayload());
        Auth::logout();

        $response = $this->postJson($this->registerRoute(), $this->validPayload([
            'email' => 'different@example.com',
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors('username');
    }

    public function test_response_contains_user_id_and_email(): void
    {
        Mail::fake();
        Event::fake();

        $response = $this->postJson($this->registerRoute(), $this->validPayload());

        $response->assertStatus(200)
            ->assertJson([
                'email' => 'test@example.com',
            ])
            ->assertJsonStructure(['user_id', 'email']);
    }
}
