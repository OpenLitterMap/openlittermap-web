<?php

namespace App\Models\Leaderboard;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * This model does not have a table at the moment
 *
 * The leaderboard is stored on Redis
 */
class Leaderboard extends Model
{
    use HasFactory;

    /**
     * Gets the users from the given ids
     * Attaches to each their global or location-based XP
     * Formats them for display on the leaderboards
     */
    public static function getLeadersByUserIds (array $userIds): array
    {
        $users = User::query()
            ->with(['teams:id,name'])
            ->whereIn('id', array_keys($userIds))
            ->get();

        return collect($userIds)
            ->map(function ($xp, $userId) use ($users) {
                /** @var User $user */
                $user = $users->firstWhere('id', $userId);
                if (!$user) {
                    return null;
                }

                $showTeamName = $user->active_team && $user->teams
                        ->where('pivot.team_id', $user->active_team)
                        ->first(function ($value, $key) {
                            return $value->pivot->show_name_leaderboards || $value->pivot->show_username_leaderboards;
                        });

                return [
                    'name' => $user->show_name ? $user->name : '',
                    'username' => $user->show_username ? ('@' . $user->username) : '',
                    'xp' => number_format($xp),
                    'global_flag' => $user->global_flag,
                    'social' => empty($user->social_links) ? null : $user->social_links,
                    'team' => $showTeamName ? $user->team->name : ''
                ];
            })
            ->filter(function ($user) {
                return $user && $user['xp'] > 0;
            })
            ->values()
            ->toArray();
    }
}
