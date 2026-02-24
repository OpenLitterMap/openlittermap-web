<?php

namespace Tests\Feature\Signup;

use Tests\TestCase;

class CreateNewUserTest extends TestCase
{
    public function test_user_can_create_account_with_valid_password()
    {
        $response = $this->postJson('/api/register', [
            'name' => 'John Doe',
            'username' => 'username_' . time(),
            'email' => 'test_' . time() . '@example.com',
            'password' => 'pass5',
            'password_confirmation' => 'pass5',
        ]);

        $response->assertOk();
    }

    public function test_user_cannot_create_account_with_short_password()
    {
        $response = $this->postJson('/api/register', [
            'name' => 'John Doe',
            'username' => 'username_' . time(),
            'email' => 'test_' . time() . '@example.com',
            'password' => 'pass',
            'password_confirmation' => 'pass',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['password']);
    }
}
