<?php

namespace App\Http\Controllers\Cleanups;

use App\Events\Cleanups\CleanupCreated;
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
            'name' => 'required|min:5',
            'date' => 'required',
            'lat' => 'required',
            'lon' => 'required',
            'time' => 'required|min:3',
            'description' => 'required|min:5',
            'invite_link' => 'required|unique:cleanups|min:1'
        ]);

        $user = auth()->user();

        $cleanup = Cleanup::create([
            'user_id' => $user->id,
            'name' => $request->name,
            'date' => $request->date,
            'lat' => $request->lat,
            'lon' => $request->lon,
            'description' => $request->description,
            'invite_link' => $request->invite_link
        ]);

        // Event: A new cleanup has been created
        event (new CleanupCreated(
            $cleanup->name,
            $cleanup->lat,
            $cleanup->lon
        ));

        // User joins the cleanup event
        $cleanup->users()->attach($user);

        return [
            'success' => true,
            'cleanup' => $cleanup
        ];
    }
}
