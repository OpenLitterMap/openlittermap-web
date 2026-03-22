<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EmailUpdate extends Mailable
{
    use Queueable, SerializesModels;

    public $user;

    public function __construct($user)
    {
        $this->user = $user;
    }

    public function build(): static
    {
        return $this->from('hello@openlittermap.com', 'Seán @ OpenLitterMap')
            ->subject("OpenLitterMap v5 is now online \xF0\x9F\x9A\x80")
            ->view('emails.update26');
    }
}
