<?php

namespace Tests\Feature\Signup;

use Tests\TestCase;

use PHPUnit\Framework\Attributes\Group;

#[Group('deprecated')]
class CreateNewUserTest extends TestCase
{
    public function test_user_can_create_account_with_valid_password()
    {
        $response = $this->withoutMiddleware()->post('/register', [
            'name' => 'John Doe',
            'username' => 'username_' . time(),
            'email' => 'test_' . time() . '@example.com',
            'password' => 'pass5',
            'password_confirmation' => 'pass5',
        ]);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_user_cannot_create_account_with_short_password()
    {
        $response = $this->withoutMiddleware()->post('/register', [
            'name' => 'John Doe',
            'username' => 'username_' . time(),
            'email' => 'test_' . time() . '@example.com',
            'password' => 'pass',
            'password_confirmation' => 'pass',
        ]);

        $this->assertEquals(302, $response->getStatusCode());

        $errors = $response->getSession()->get('errors')->toArray();
        $this->assertArrayHasKey('password', $errors);
        $this->assertStringContainsString('at least 5 characters', $errors['password'][0]);
    }
}
