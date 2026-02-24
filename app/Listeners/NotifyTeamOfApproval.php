<?php

namespace App\Listeners;

use App\Events\SchoolDataApproved;
use App\Notifications\SchoolDataApprovedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyTeamOfApproval implements ShouldQueue
{
    public function handle(SchoolDataApproved $event): void
    {
        // Notify all team members
        $members = $event->team->users;

        foreach ($members as $member) {
            $member->notify(new SchoolDataApprovedNotification($event));
        }
    }
}
