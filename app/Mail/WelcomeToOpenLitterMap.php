<?php

namespace App\Mail;

use App\Models\Users\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WelcomeToOpenLitterMap extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public User $user) {}

    public function build(): static
    {
        return $this->from('welcome@openlittermap.com')
            ->subject('Welcome to OpenLitterMap')
            ->markdown('emails.welcome', [
                'verifyUrl' => route('confirm-email-token', $this->user->token),
                'uploadUrl' => url('/upload'),
                'mapUrl' => url('/global'),
                'profileUrl' => url('/profile'),
                'unsubscribeUrl' => url('/emails/unsubscribe/' . $this->user->sub_token),
                'litterweekUrl' => 'https://litterweek.org',
                'username' => $this->user->username,
            ]);
    }
}
