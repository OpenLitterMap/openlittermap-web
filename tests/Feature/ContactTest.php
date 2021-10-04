<?php

namespace Tests\Feature;

use Anhskohbo\NoCaptcha\Facades\NoCaptcha;
use App\Mail\ContactMail;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactTest extends TestCase
{
    public function test_users_can_see_the_contact_page()
    {
        $response = $this->get('/contact-us');

        $response->assertStatus(200);
    }

    public function test_users_can_send_a_contact_mail()
    {
        Mail::fake();

        // prevent validation error on captcha
        NoCaptcha::shouldReceive('verifyResponse')
            ->once()
            ->andReturn(true);

        $response = $this->post(route('contact'), [
            'subject' => 'Test subject',
            'message' => 'Test message',
            'name' => 'Guest name',
            'email' => 'guest@email.com',
            'g-recaptcha-response' => '1'
        ]);

        $response->assertOk();

        Mail::assertSent(
            ContactMail::class,
            function (ContactMail $mail) {
                return $mail->hasTo('info@openlittermap.com') &&
                    $mail->name === 'Guest name' &&
                    $mail->email === 'guest@email.com' &&
                    $mail->subject === 'Test subject' &&
                    $mail->message === 'Test message';
            }
        );
    }

    /**
     * @dataProvider validationData
     * @param $fields
     * @param $errors
     */
    public function test_request_values_are_validated($fields, $errors)
    {
        Mail::fake();

        // prevent validation error on captcha
        NoCaptcha::shouldReceive('verifyResponse')
            ->zeroOrMoreTimes()
            ->andReturn(true);

        $response = $this->postJson(route('contact'), $fields);

        $response->assertStatus(422);

        $response->assertJsonValidationErrors($errors);

        Mail::assertNothingSent();
    }

    public function validationData(): array
    {
        return [
            [
                // Missing subject
                'fields' => ['message' => 'message', 'email' => 'guest@email.com', 'g-recaptcha-response' => '1'],
                'errors' => ['subject']
            ],
            [
                // Missing message
                'fields' => ['subject' => 'subject', 'email' => 'guest@email.com', 'g-recaptcha-response' => '1'],
                'errors' => ['message']
            ],
            [
                // Missing email
                'fields' => ['subject' => 'subject', 'message' => 'message', 'g-recaptcha-response' => '1'],
                'errors' => ['email']
            ],
            [
                // Malformed email
                'fields' => ['subject' => 'subject', 'message' => 'message', 'email' => 'malformed email', 'g-recaptcha-response' => '1'],
                'errors' => ['email']
            ],
            [
                // Missing recaptcha
                'fields' => ['subject' => 'subject', 'message' => 'message', 'email' => 'guest@email.com'],
                'errors' => ['g-recaptcha-response']
            ],
        ];
    }

}
