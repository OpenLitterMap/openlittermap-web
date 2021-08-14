<?php

namespace App\Http\Controllers\User\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SocialMediaController extends Controller
{
    /**
     * Update the users social media links
     *
     * @param Request $request
     *
     * @return array
     */
    public function update (Request $request): array
    {
        try
        {
            $user = Auth::user();

            $user->settings->twitter = $request->twitter;
            $user->settings->instagram = $request->instagram;
            $user->settings->save();

            return [
                'success' => true
            ];
        }
        catch (\Exception $e)
        {
            \Log::info(['SocialMediaController@update', $e->getMessage()]);

            return [
                'success' => false
            ];
        }
    }
}
