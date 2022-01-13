<?php

namespace App\Http\Controllers\Teams;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TeamsSettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Apply settings to 1 or all teams
     *
     * @return array
     */
    public function index (Request  $request)
    {
        $user = Auth::user();

        if ($request->all)
        {
            foreach ($user->teams as $team)
            {
                DB::table('team_user')->where([
                    'team_id' => $team->id,
                    'user_id' => $user->id
                ])->update([
                    'show_name_maps' => $request->settings['show_name_maps'],
                    'show_username_maps' => $request->settings['show_username_maps'],
                    'show_name_leaderboards' => $request->settings['show_name_leaderboards'],
                    'show_username_leaderboards' => $request->settings['show_username_leaderboards'],
                ]);
            }
        }

        else
        {
            DB::table('team_user')->where([
                'team_id' => $request->team_id,
                'user_id' => $user->id
            ])->update([
                'show_name_maps' => $request->settings['show_name_maps'],
                'show_username_maps' => $request->settings['show_username_maps'],
                'show_name_leaderboards' => $request->settings['show_name_leaderboards'],
                'show_username_leaderboards' => $request->settings['show_username_leaderboards'],
            ]);
        }

        return ['success' => true];
    }
}
