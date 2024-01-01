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

        $this->assertSame($subject, $mail->subject);
        $this->assertSame($message, $mail->message);
        $this->assertSame($name, $mail->name);
        $this->assertSame($email, $mail->email);
    }
}
