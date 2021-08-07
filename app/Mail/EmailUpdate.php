<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class EmailUpdate extends Mailable
{
    use Queueable, SerializesModels;

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
            ->subject('Update #24 - $50,000 funding from Cardano. Partnership with University College Dublin')
            ->view('emails.update24')
            ->attach(public_path('/attachments/UCD_SURVEY.pdf'));
    }
}
