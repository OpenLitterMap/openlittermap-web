<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class RequestToJoinNewTeam extends Mailable
{
    use Queueable;
    use SerializesModels;
    public $leader;

    public $member;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($leader, $member)
    {
        $this->leader = $leader;
        $this->member = $member;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from('noreply@openlittermap.com')
            ->subject('Someone has requested to join your Team')
            ->view('emails.teams.join')
            ->with([
                'leader' => $this->leader,
                'member' => $this->member
            ]);
    }
}
