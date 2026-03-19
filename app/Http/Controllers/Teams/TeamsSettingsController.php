<?php

namespace App\Http\Controllers\Teams;

use App\Http\Controllers\Controller;
use App\Models\Teams\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TeamsSettingsController extends Controller
{
    /**
     * Apply privacy settings to one or all teams.
     *
     * Uses Eloquent pivot instead of raw DB::table queries.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'settings' => 'required|array',
            'settings.show_name_maps' => 'required|boolean',
            'settings.show_username_maps' => 'required|boolean',
            'settings.show_name_leaderboards' => 'required|boolean',
            'settings.show_username_leaderboards' => 'required|boolean',
            'team_id' => 'required_without:all|integer|exists:teams,id',
            'all' => 'nullable|boolean',
        ]);

        $user = Auth::user();
        $settings = $request->input('settings');

        $pivotData = [
            'show_name_maps' => $settings['show_name_maps'],
            'show_username_maps' => $settings['show_username_maps'],
            'show_name_leaderboards' => $settings['show_name_leaderboards'],
            'show_username_leaderboards' => $settings['show_username_leaderboards'],
        ];

        if ($request->boolean('all')) {
            // Apply to all teams the user belongs to
            foreach ($user->teams as $team) {
                $data = $this->enforceSafeguarding($team, $pivotData);
                $user->teams()->updateExistingPivot($team->id, $data);
            }
        } else {
            // Verify membership, then update single team
            if (! $user->isMemberOfTeam($request->team_id)) {
                return response()->json(['message' => 'Not a member of this team.'], 403);
            }

            $team = Team::find($request->team_id);
            $pivotData = $this->enforceSafeguarding($team, $pivotData);
            $user->teams()->updateExistingPivot($request->team_id, $pivotData);
        }

        return response()->json(['success' => true]);
    }

    /**
     * School teams with safeguarding: user names and usernames are never visible.
     */
    private function enforceSafeguarding(?Team $team, array $pivotData): array
    {
        if ($team && $team->hasSafeguarding()) {
            $pivotData['show_name_maps'] = false;
            $pivotData['show_username_maps'] = false;
            $pivotData['show_name_leaderboards'] = false;
            $pivotData['show_username_leaderboards'] = false;
        }

        return $pivotData;
    }
}
