<?php

namespace App\Mail\Admin;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AccountUpgraded extends Mailable
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
     * A user has just reached 100 verified images
     *
     * Their account has been upgraded, and they earned their first Littercoin
     */
    public function build()
    {
        return $this->from('congratulations@openlittermap.com')
            ->subject('You have earned your first Littercoin')
            ->view('emails.admin.account-upgraded')
            ->with([
                'username' => $this->user->username
            ]);
    }
}
