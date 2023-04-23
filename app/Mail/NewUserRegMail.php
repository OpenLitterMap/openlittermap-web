<?php

namespace App\Mail;

use App\Models\User\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class NewUserRegMail extends Mailable
{
    use Queueable, SerializesModels;

    // protected $_token;
    // protected $email;

    public $user;
    // pass in User to construct

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
        return $this->from('welcome@openlittermap.com')
            ->subject('Confirm your email on OpenLitterMap')
            ->view('auth.emails.confirm')
            ->with([
                'token' => $this->user->token
            ]);
    }
}
