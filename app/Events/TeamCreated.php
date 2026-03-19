<?php

namespace App\Events;

use App\Models\Teams\Team;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when a new team is created.
 *
 * PRIVACY: School teams broadcast on a PRIVATE channel only.
 * Community teams broadcast on the public 'teams' channel.
 *
 * School team names contain school identity ("St. X 1st Years 2026")
 * — broadcasting them publicly creates a directory of targets.
 */
class TeamCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public Team $team,
    ) {}

    public function broadcastOn(): Channel|PrivateChannel
    {
        if ($this->team->isSchool()) {
            return new PrivateChannel("team.{$this->team->id}");
        }

        return new Channel('teams');
    }

    public function broadcastAs(): string
    {
        return 'team.created';
    }

    public function broadcastWith(): array
    {
        // Never broadcast school name, roll number, etc.
        if ($this->team->isSchool()) {
            return ['team_id' => $this->team->id];
        }

        return [
            'team_id' => $this->team->id,
            'team_name' => $this->team->name,
        ];
    }
}
