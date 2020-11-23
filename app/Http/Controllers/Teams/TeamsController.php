<?php

namespace App\Http\Controllers\Teams;

use App\Models\Photo;
use App\Models\Teams\Team;
use App\Models\Teams\TeamType;
use App\Traits\FilterTeamMembersTrait;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\Controller;

class TeamsController extends Controller
{
    use FilterTeamMembersTrait;

    /**
     * Get the combined effort for all of your teams for the time-period
     *
     * Should we only count verified photos?
     */
    public function combined ()
    {
        $user = Auth::user();

        $ids = $user->teams->pluck('id'); // array of team_ids

        // period
        if (request()->period === 'today') $period = now()->startOfDay();
        else if (request()->period === 'week') $period = now()->startOfWeek();
        else if (request()->period === 'month') $period = now()->startOfMonth();
        else if (request()->period === 'year') $period = now()->startOfYear();
        else if (request()->period === 'all') $period = '2020-11-22 00:00:00'; // date of writing

        $query = Photo::whereIn('team_id', $ids)
            ->whereDate('created_at', '>=', $period)
            ->where('verified', 2);

        $photos_count = $query->count();
        $members_count = $query->distinct()->count('user_id');

        // might need photo.verified_at
        $litter_count = Photo::whereIn('team_id', $ids)
            ->whereDate('updated_at', '>=', $period)
            ->where('verified', 2)
            ->sum('total_litter');

        return [
            'photos_count' => $photos_count,
            'litter_count' => $litter_count,
            'members_count' => $members_count
        ];
    }

    /**
     * The user wants to create a new team
     */
    public function create (Request $request)
    {
        $request->validate([
            'name' => 'required|min:3|max:100|unique:teams',
            'identifier' => 'required|min:3|max:15|unique:teams',
            'teamType' => 'required'
        ]);

        $user = Auth::user();

        if ($user->remaining_teams === 0) return ['success' => false, 'msg' => 'max-created'];

        $team = Team::create([
            'created_by' => $user->id,
            'name' => $request->name,
            'type_id' => $request->teamType,
            'leader' => $user->id,
            'identifier' => $request->identifier
        ]);

        // Have the user join this team
        $user->teams()->attach($team);

        $user->active_team = $team->id;
        $user->remaining_teams--;
        $user->save();

        return ['success' => true, 'team' => $team];
    }

    /**
     * The user wants to join a new team
     */
    public function join (Request $request)
    {
        $request->validate([
            'identifier' => 'required|min:3|max:100'
        ]);

        $user = Auth::user();

        if ($team = Team::where('identifier', $request->identifier)->first())
        {
            // Check the user is not already in the team
            foreach ($user->teams as $t)
            {
                if ($team->id === $t->id) return ['success' => false, 'msg' => 'already-joined'];
            }

            // Have the user join this team
            $user->teams()->attach($team);

            return ['success' => true];
        }

        return ['success' => false, 'msg' => 'not-found'];
    }

    /**
     * Array of teams the user has joined
     */
    public function joined ()
    {
        return Auth::user()->teams;
    }

    /**
     * Get paginated members for a team_id
     */
    public function members ()
    {
        $query = $this->filterTeamMembers(request()->team_id);

        $total_members = $query->users->count();

        $result = $query->users()
            ->withPivot('total_photos', 'total_litter')
            ->orderBy('pivot_total_litter')
            ->simplePaginate(10, [
                // include these fields
                'users.id',
                'users.email',
                'users.name',
                'users.username',
                'users.updated_at', // todo add users.last_uploaded
                'total_photos'
            ]);

        return [
            'total_members' => $total_members,
            'result' => $result
        ];
    }

    /**
     * Return the types of available teams
     */
    public function types ()
    {
        return TeamType::select('id', 'team')->get();
    }
}
