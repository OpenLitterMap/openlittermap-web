<?php

namespace App\Http\Controllers\Teams;

use App\Actions\Teams\CreateTeamAction;
use App\Actions\Teams\JoinTeamAction;
use App\Actions\Teams\LeaveTeamAction;
use App\Actions\Teams\SetActiveTeamAction;
use App\Actions\Teams\UpdateTeamAction;
use App\Exports\CreateCSVExport;
use App\Http\Requests\Teams\CreateTeamRequest;
use App\Http\Requests\Teams\JoinTeamRequest;
use App\Http\Requests\Teams\LeaveTeamRequest;
use App\Http\Requests\Teams\UpdateTeamRequest;
use App\Jobs\EmailUserExportCompleted;
use App\Models\Teams\Team;
use App\Models\Teams\TeamType;
use App\Models\User\User;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\Controller;

class TeamsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Change the users currently active team
     *
     * @return array
     */
    public function active (Request $request)
    {
        /** @var User $user */
        $user = auth()->user();
        /** @var Team $team */
        $team = Team::find($request->team_id);

        $isTeamMember = $user->teams()->where('team_id', $request->team_id)->exists();

        if (!$isTeamMember) {
            return ['success' => false];
        }

        /** @var SetActiveTeamAction $action */
        $action = app(SetActiveTeamAction::class);
        $action->run($user, $request->team_id);

        return ['success' => true, 'team' => $team];
    }

    /**
     * Clears the user's active team
     *
     * @return array
     */
    public function inactivateTeam()
    {
        /** @var User $user */
        $user = auth()->user();

        $user->active_team = null;
        $user->save();

        return ['success' => true];
    }

    /**
     * The user wants to create a new team
     *
     * @return array
     */
    public function create (CreateTeamRequest $request, CreateTeamAction $action)
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user->remaining_teams === 0) return ['success' => false, 'msg' => 'max-created'];

        $team = $action->run($user, $request->all());

        return ['success' => true, 'team' => $team];
    }

    /**
     * The user wants to update a team
     *
     * @param UpdateTeamRequest $request
     * @param UpdateTeamAction $action
     * @param Team $team
     * @return array
     */
    public function update (UpdateTeamRequest $request, UpdateTeamAction $action, Team $team)
    {
        if (auth()->id() != $team->leader) {
            abort(403, 'You are not the team leader!');
        }

       $team = $action->run($team, $request->all());

        return ['success' => true, 'team' => $team];
    }

    /**
     * The user wants to download data from a specific team
     *
     * @return array
     */
    public function download (Request $request)
    {
        $email = auth()->user()->email;

        $x     = new \DateTime();
        $date  = $x->format('Y-m-d');
        $date  = explode('-', $date);
        $year  = $date[0];
        $month = $date[1];
        $day   = $date[2];
        $unix  = now()->timestamp;

        $path = $year.'/'.$month.'/'.$day.'/'.$unix.'/';  // 2020/10/25/unix/

        $path .= '_Team_OpenLitterMap.csv';

        /* Dispatch job to create CSV file for export */
        (new CreateCSVExport($request->type, null, $request->team_id))
            ->queue($path, 's3', null, ['visibility' => 'public'])
            ->chain([
                // These jobs are executed when above is finished.
                new EmailUserExportCompleted($email, $path)
                // new ....job
            ]);

        return ['success' => true];
    }

    /**
     * The user wants to join a team
     *
     * @return array
     */
    public function join (JoinTeamRequest $request, JoinTeamAction $action)
    {
        /** @var User $user */
        $user = Auth::user();
        /** @var Team $team */
        $team = Team::whereIdentifier($request->identifier)->first();

        // Check the user is not already in the team
        if ($user->teams()->whereTeamId($team->id)->exists())
        {
            return ['success' => false, 'msg' => 'already-joined'];
        }

       $action->run($user, $team);

        return [
            'success' => true,
            'team' => $team->fresh(),
            'activeTeam' => $user->fresh()->team()->first()
        ];
    }

    /**
     * The user wants to leave a team
     *
     * @return array
     */
    public function leave (LeaveTeamRequest $request, LeaveTeamAction $action)
    {
        /** @var User $user */
        $user = auth()->user();
        /** @var Team $team */
        $team = Team::find($request->team_id);

        if (!$user->teams()->whereTeamId($request->team_id)->exists()) {
            abort(403, 'You are not part of this team!');
        }

        if ($team->users()->count() <= 1) {
            abort(403, 'You are the only member of this team!');
        }

        $action->run($user, $team);

        return [
            'success' => true,
            'team' => $team->fresh(),
            'activeTeam' => $user->fresh()->team()->first()
        ];
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
     *
     * We need to check the privacy settings for each user on the team,
     * and only load the columns (show_name_leaderboard) that each user has allowed.
     *
     * @return array
     */
    public function members ()
    {
        $team = Team::query()->find(request()->team_id);

        $total_members = $team->users->count(); // members?

        $result = $team->users()
            ->withPivot('total_photos', 'total_litter', 'updated_at', 'show_name_leaderboards', 'show_username_leaderboards')
            ->orderBy('pivot_total_litter', 'desc')
            ->simplePaginate(10, [
                // include these fields
                'users.id',
                'users.name', // todo - only load this if team_user.show_name is true
                'users.username', // todo - only load this if team_user.show_username is true
                'users.active_team',
                'users.updated_at', // todo add users.last_uploaded
                'total_photos'
            ]);

        // We need to filter out name/username based on the settings
        // For now, just remove them manually with a loop.
        // We should figure out how to do this in the query
        // https://stackoverflow.com/questions/65371551/filter-simplepaginate-column-select-by-pivot-table
        foreach ($result as $r)
        {
            if (! $r->pivot->show_name_leaderboards) $r->name = null;
            if (! $r->pivot->show_username_leaderboards) $r->username = null;
        }

        return [
            'total_members' => $total_members,
            'result' => $result
        ];
    }

    /**
     * Return the types of available teams
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function types ()
    {
        return TeamType::select('id', 'team')->get();
    }
}
