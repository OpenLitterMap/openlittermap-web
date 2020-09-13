<?php

namespace App\Mail;

use App\Models\User\User;
use App\Team;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class NewTeamCreated extends Mailable
{
    use Queueable, SerializesModels;

    public $team;
    public $user;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($team, $user)
    {
        $this->team = $team;
        $this->user = $user;
    }

    /**
     * Build the message.
     *
     * @return $this

     // different emails per team

     */
    public function build()
    {
        return $this->from('noreply@openlittermap.com')
            ->subject('Your New Team has been created!')
            ->view('emails.teams.create')
            ->with([
                'user' => $this->user->name,
                'team' => $this->team->name
            ]);
    }
}
