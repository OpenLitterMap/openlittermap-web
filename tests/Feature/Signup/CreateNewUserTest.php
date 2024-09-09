<?php

namespace Tests\Feature\Signup;

use Tests\TestCase;

class CreateNewUserTest extends TestCase
{
    public function test_a_user_can_create_an_account ()
    {
        $response = $this->withoutMiddleware()->post('/register', [
            'name' => 'John Doe',
            'username' => 'username_' . time(),
            'email' => 'test_' . time() . '@example.com',
            'password' => 'ReallyStrongPassword123!',
            'password_confirmation' => 'password',
        ]);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public static function passwordProvider (): array
    {
        return [
            'missing_uppercase' => [
                'password' => 'lowercase1#',
                'error' => 'validation.password.mixed'
            ],
            'missing_lowercase' => [
                'password' => 'UPPERCASE1#',
                'error' => 'validation.password.mixed'
            ],
            'missing_numbers' => [
                'password' => 'UpperLower#',
                'error' => 'validation.password.numbers'
            ],
            'missing_symbols' => [
                'password' => 'UpperLower1',
                'error' => 'validation.password.symbols'
            ],
        ];
    }

    /**
     * @dataProvider passwordProvider
     */
    public function test_a_user_cannot_create_an_account_with_invalid_password ($password, $error)
    {
        $response = $this->withoutMiddleware()->post('/register', [
            'name' => 'John Doe',
            'username' => 'username_' . time(),
            'email' => 'test_' . time() . '@example.com',
            'password' => $password,
            'password_confirmation' => 'password',
        ]);

        $this->assertEquals(302, $response->getStatusCode());

        $errors = $response->getSession()->get('errors')->toArray();

        $this->assertArrayHasKey('password', $errors);

        $this->assertTrue(in_array($error, $errors['password']));
    }
}
