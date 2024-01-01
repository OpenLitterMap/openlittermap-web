<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class EmailUpdate extends Mailable
{
    use Queueable;
    use SerializesModels;
    public $user;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct ($user)
    {
        $this->user = $user;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from('info@openlittermap.com', 'OpenLitterMap')
            ->subject('Update #25 - Big Improvements since $50,000 funding from cryptocurrency Cardano')
            ->view('emails.update25');
    }
}
