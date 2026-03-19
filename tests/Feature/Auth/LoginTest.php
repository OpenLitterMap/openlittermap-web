<?php

namespace Tests\Feature\Auth;

use App\Models\Users\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_login_with_email()
    {
        $user = User::factory()->create([
            'email' => 'sean@openlittermap.com',
            'password' => 'password123',
        ]);

        $this->postJson('/api/auth/login', [
            'identifier' => 'sean@openlittermap.com',
            'password' => 'password123',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.id', $user->id);

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_user_can_login_with_username()
    {
        $user = User::factory()->create([
            'username' => 'seanlynch',
            'password' => 'password123',
        ]);

        $this->postJson('/api/auth/login', [
            'identifier' => 'seanlynch',
            'password' => 'password123',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.id', $user->id);

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_wrong_password()
    {
        User::factory()->create([
            'email' => 'sean@openlittermap.com',
            'password' => 'password123',
        ]);

        $this->postJson('/api/auth/login', [
            'identifier' => 'sean@openlittermap.com',
            'password' => 'wrong_password',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['identifier']);

        $this->assertGuest();
    }

    public function test_login_fails_with_nonexistent_user()
    {
        $this->postJson('/api/auth/login', [
            'identifier' => 'nobody@example.com',
            'password' => 'password123',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['identifier']);
    }

    public function test_login_validates_required_fields()
    {
        $this->postJson('/api/auth/login', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['identifier', 'password']);
    }

    public function test_login_is_rate_limited()
    {
        User::factory()->create(['email' => 'sean@openlittermap.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', [
                'identifier' => 'sean@openlittermap.com',
                'password' => 'wrong',
            ]);
        }

        $this->postJson('/api/auth/login', [
            'identifier' => 'sean@openlittermap.com',
            'password' => 'wrong',
        ])->assertStatus(429);
    }

    public function test_a_user_can_logout()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertGuest();
    }
}
