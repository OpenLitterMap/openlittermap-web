<?php

namespace App\Http\Controllers;

use Auth;
use App\User;
use App\Team;
use App\TeamType;
use App\Mail\NewTeamCreated;
use Illuminate\Http\Request;
use App\Mail\RequestToJoinNewTeam;
use Illuminate\Support\Facades\Mail;

class TeamController extends Controller
{

    // /**
    //  * Get the currently active Team
    //  */
    // public function get($id) {
    //     $team = Team::find($id);
    //     return $team;
    // }


	/**
	 * Create and register a new Team 
	 */
    public function create(Request $request) {

		// return $request;
        $teamtype = TeamType::find($request->team);

    	try {

            $this->validate($request, [
                'stripeEmail' => 'required|email',
                'stripeToken' => 'required',
                       'team' => 'required'
            ]);

            $user = \App\User::where('email', $request->stripeEmail)->first();

    	} catch(Exception $e) {
    		// return $e->getMessage();
    		return response()->json(['status' => $e->getMessage()], 422);
    	}

        $user->newSubscription($teamtype->team, $teamtype->team)->create($request->stripeToken); 
        // could pass more args here as second item ($token, []..);
        $team = Team::create([
        	'name' => $request->teamname,
        	'type_id' => $request->team,
        	'type_name' => $teamtype->team,
        	'leader' => $user->id
        ]);

        $user->teams()->save($team);

        Mail::to($user->email)->send(new NewTeamCreated($teamtype, $user));

    	// return ['status' => 'Success!'];
    }

    /**
     * Request to join a new team 
     */
    public function request(Request $request) {
        $this->validate($request, [
            'email' => 'required|email',
            'tlemail' => 'required|email',
        ]);

        $tlemail = $request['tlemail'];
        $teamleader = User::where('email', $tlemail)->first();

        $member = Auth::user();

        if($teamleader) {
            if($teamleader->teams->count() > 0) {
                Mail::to($teamleader)->send(new RequestToJoinNewTeam($teamleader->name, $member->name));
                return "Success";
            }
        }

        return "Error";
    }


    /**
     * Change active team 
     */
    public function change(Request $request) {
        $user = Auth::user();
        $newTeam = $request["newteam"];
        $team = Team::where('name', $newTeam)->first();
        $user->active_team = $team->id;
        $user->save();
        return ['message' => 'Success'];
    }




}
