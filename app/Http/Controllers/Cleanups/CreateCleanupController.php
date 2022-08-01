<?php

namespace App\Http\Controllers\Cleanups;

use App\Http\Controllers\Controller;
use App\Models\Cleanups\Cleanup;
use Illuminate\Http\Request;

class CreateCleanupController extends Controller
{
    /**
     * Create a new cleanup event
     */
    public function __invoke (Request $request)
    {
        $request->validate([
            'name' => 'required',
            'date' => 'required',
            'lat' => 'required',
            'lon' => 'required',
            'inviteLink' => 'required'
        ]);

        $user = auth()->user();

        $cleanup = Cleanup::create([
            'user_id' => $user->id,
            'name' => $request->name,
            'date' => $request->date,
            'lat' => $request->lat,
            'lon' => $request->lon,
            'description' => $request->description,
            'invite_link' => $request->inviteLink
        ]);

        // Event: A new cleanup has been created

        // User joins the cleanup event
        $user->cleanups()->attach($cleanup);

        return [
            'success' => true,
            'cleanup' => $cleanup
        ];
    }
}
