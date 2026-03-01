<?php

namespace App\Mail;

use App\Models\Users\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class V5AnnouncementMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public User $user) {}

    public function build(): static
    {
        return $this->from('welcome@openlittermap.com')
            ->subject('OpenLitterMap just got its biggest upgrade ever')
            ->markdown('emails.v5-announcement', [
                'uploadUrl' => url('/upload'),
                'profileUrl' => url('/profile'),
                'unsubscribeUrl' => url('/emails/unsubscribe/' . $this->user->sub_token),
                'litterweekUrl' => 'https://litterweek.org',
            ]);
    }
}
