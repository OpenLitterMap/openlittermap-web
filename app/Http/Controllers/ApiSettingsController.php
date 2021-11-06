<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApiSettingsController extends Controller
{
    /**
     * Toggle privacy of users name on the maps
     */
    public function mapsName (Request $request)
    {
		$user = Auth::guard('api')->user();
        $user->show_name_maps = !$user->show_name_maps;
        $user->save();
    	return ['show_name_maps' => $user->show_name_maps];
    }

    /**
     * Toggle privacy of users username on the maps
     */
    public function mapsUsername(Request $request)
    {
		$user = Auth::guard('api')->user();
    	$user->show_username_maps = ! $user->show_username_maps;
    	$user->save();
    	return ['show_username_maps' => $user->show_username_maps];
    }

    /**
     * Toggle privacy of users name on the leaderboards
     */
    public function leaderboardName(Request $request)
    {
		$user = Auth::guard('api')->user();
    	$user->show_name = ! $user->show_name;
    	$user->save();
    	return ['show_name' => $user->show_name];
    }

    /**
     * Toggle privacy of users username on the leaderboards
     */
    public function leaderboardUsername(Request $request)
    {
		$user = Auth::guard('api')->user();
    	$user->show_username = ! $user->show_username;
    	$user->save();
    	return ['show_username' => $user->show_username];
    }

    /**
     * Toggle privacy of users name on the createdBy section of a location
     */
    public function createdByName(Request $request)
    {
		$user = Auth::guard('api')->user();
		$user->show_name_createdby = ! $user->show_name_createdby;
		$user->save();
    	return ['show_name_createdby' => $user->show_name_createdby];
    }

    /**
     * Toggle privacy of users username on the createdBy section of a location
     */
    public function createdByUsername(Request $request)
    {
		$user = Auth::guard('api')->user();
    	$user->show_username_createdby = ! $user->show_username_createdby;
    	$user->save();
    	return ['show_username_createdby' => $user->show_username_createdby];
    }

    /**
     * Update a setting
     *
     * Todo - Needs validation
     */
    public function update (Request $request)
    {
        $user = Auth::guard('api')->user();

        $key = $request['key'];
        $value = $request['value'];

        if ($key == 'picked_up') {
            $key = 'items_remaining';
            $value = !$value;
        }

        try
        {
            $user->$key = $value;
            $user->save();
        }
        catch (\Exception $e)
        {
            \Log::info(['ApiSettingsController@update', $e->getMessage()]);

            return ['success' => false, 'msg' => $e->getMessage()];
        }

        return ['success' => true];
    }

    /**
     * The user will see the previous tags on the next image
     */
    public function togglePreviousTags (Request $request)
    {
        $user = Auth::guard('api')->user();

        $user->previous_tags = ! $user->previous_tags;

        $user->save();

        return ['previous_tags' => $user->previous_tags];
    }
}
