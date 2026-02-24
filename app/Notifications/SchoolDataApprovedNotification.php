<?php

namespace App\Notifications;

use App\Events\SchoolDataApproved;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SchoolDataApprovedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private SchoolDataApproved $event,
    ) {}

    public function via(): array
    {
        return ['database'];
    }

    public function toDatabase(): array
    {
        return [
            'type' => 'school_data_approved',
            'team_id' => $this->event->team->id,
            'team_name' => $this->event->team->name,
            'photo_count' => $this->event->photoCount,
            'approved_by' => $this->event->approvedBy->name,
            'message' => "{$this->event->team->name} published {$this->event->photoCount} photos to the global map.",
        ];
    }
}
