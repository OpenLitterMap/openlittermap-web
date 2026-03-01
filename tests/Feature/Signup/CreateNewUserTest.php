<?php

namespace Tests\Feature\Signup;

use Tests\TestCase;

class CreateNewUserTest extends TestCase
{
    public function test_user_can_create_account_with_valid_password()
    {
        $response = $this->postJson('/api/register', [
            'email' => 'test_' . time() . '@example.com',
            'password' => 'password8',
        ]);

        $response->assertOk();
    }

    public function test_user_cannot_create_account_with_short_password()
    {
        $response = $this->postJson('/api/register', [
            'email' => 'test_' . time() . '@example.com',
            'password' => 'short',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['password']);
    }
}
