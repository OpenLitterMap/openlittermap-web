<?php

namespace App\Http\Controllers\User\Settings;

use App\Models\User\UserSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\Controller;

class PublicProfileController extends Controller
{
    /**
     * Change the private status of a users Public Profile
     *
     * @param Request $request
     *
     * @return array
     */
    public function toggle (Request $request): array
    {
        try
        {
            $user = Auth::user();

            $settings = UserSettings::firstOrCreate(['user_id' => $user->id]);

            $settings->show_public_profile = ! $settings->show_public_profile;
            $settings->save();

            return [
                'success' => true,
                'settings' => $settings
            ];
        }
        catch (\Exception $e)
        {
            \Log::info(['PublicProfileController@toggle', $e->getMessage()]);

            return ['success' => false];
        }
    }

    /**
     * Update the settings on the users public profile
     *
     * @param Request $request
     *
     * @return array
     *
     * Todo:
     * - add validation
     * - only update what params were changed
     */
    public function update (Request $request): array
    {
        try
        {
            $user = Auth::user();

            $user->settings->public_profile_download_my_data = $request->download ?: false;
            $user->settings->public_profile_show_map = $request->map ?: false;
            $user->settings->save();

            return [
                'success' => true
            ];
        }
        catch (\Exception $e)
        {
            \Log::info(['PublicProfileController@update', $e->getMessage()]);

            return [
                'success' => false
            ];
        }
    }
}
