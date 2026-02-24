<?php

namespace App\Events;

use App\Models\Teams\Team;
use App\Models\Users\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when a teacher approves school team photos for publication.
 *
 * PRIVACY: Broadcasts on a PRIVATE channel only.
 * School team names must never appear on public channels —
 * they contain school identity ("St. X 1st Years 2026") which
 * creates a directory of targets for join-code guessing.
 */
class SchoolDataApproved implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public Team $team,
        public User $approvedBy,
        public int $photoCount,
    ) {}

    /**
     * Private channel — only authenticated team members can listen.
     * Requires channel authorization in BroadcastServiceProvider:
     *
     *   Broadcast::channel('team.{teamId}', function ($user, $teamId) {
     *       return $user->teams()->where('team_id', $teamId)->exists();
     *   });
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("team.{$this->team->id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'school.data.approved';
    }

    public function broadcastWith(): array
    {
        return [
            'team_id' => $this->team->id,
            'photo_count' => $this->photoCount,
            // Do NOT include team name or school identity in broadcast payload
        ];
    }
}
