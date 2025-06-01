<?php

namespace Tests\Unit\Mail;

use App\Mail\ContactMail;
use Tests\TestCase;

class ContactTest extends TestCase
{
    public function test_it_contains_necessary_content()
    {
        $subject = 'test subject';
        $message = 'test message';
        $name = 'test name';
        $email = 'test email';

        $mail = (new ContactMail($subject, $message, $name, $email))->build();

        $this->assertEquals($subject, $mail->subject);
        $this->assertEquals($message, $mail->message);
        $this->assertEquals($name, $mail->name);
        $this->assertEquals($email, $mail->email);
    }
}
