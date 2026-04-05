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
        return $this->from('info@openlittermap.com', 'Seán @ OpenLitterMap')
            ->subject('Update 27 - Mobile app updates & more!')
            ->view('emails.update27');
    }
}
