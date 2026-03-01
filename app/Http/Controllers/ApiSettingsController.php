<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ApiSettingsController extends Controller
{
    /**
     * Allowed keys and their validation rules for the update endpoint.
     */
    private const ALLOWED_SETTINGS = [
        'name' => 'string|min:3|max:25',
        'username' => 'string|min:3|max:30|regex:/^[a-zA-Z0-9-]+$/',
        'email' => 'email|max:75',
        'global_flag' => 'nullable|string|max:10',
        'picked_up' => 'boolean',
        'previous_tags' => 'boolean',
        'emailsub' => 'boolean',
        'public_profile' => 'boolean',
        'prevent_others_tagging_my_photos' => 'boolean',
    ];

    /**
     * Toggle privacy of users name on the maps
     */
    public function mapsName(Request $request): array
    {
        $user = Auth::user();
        $user->show_name_maps = ! $user->show_name_maps;
        $user->save();

        return ['show_name_maps' => $user->show_name_maps];
    }

    /**
     * Toggle privacy of users username on the maps
     */
    public function mapsUsername(Request $request): array
    {
        $user = Auth::user();
        $user->show_username_maps = ! $user->show_username_maps;
        $user->save();

        return ['show_username_maps' => $user->show_username_maps];
    }

    /**
     * Toggle privacy of users name on the leaderboards
     */
    public function leaderboardName(Request $request): array
    {
        $user = Auth::user();
        $user->show_name = ! $user->show_name;
        $user->save();

        return ['show_name' => $user->show_name];
    }

    /**
     * Toggle privacy of users username on the leaderboards
     */
    public function leaderboardUsername(Request $request): array
    {
        $user = Auth::user();
        $user->show_username = ! $user->show_username;
        $user->save();

        return ['show_username' => $user->show_username];
    }

    /**
     * Toggle privacy of users name on the createdBy section of a location
     */
    public function createdByName(Request $request): array
    {
        $user = Auth::user();
        $user->show_name_createdby = ! $user->show_name_createdby;
        $user->save();

        return ['show_name_createdby' => $user->show_name_createdby];
    }

    /**
     * Toggle privacy of users username on the createdBy section of a location
     */
    public function createdByUsername(Request $request): array
    {
        $user = Auth::user();
        $user->show_username_createdby = ! $user->show_username_createdby;
        $user->save();

        return ['show_username_createdby' => $user->show_username_createdby];
    }

    /**
     * Update a setting by key/value with whitelist validation.
     */
    public function update(Request $request): JsonResponse
    {
        $user = Auth::user();

        $key = $request->input('key');
        $value = $request->input('value');

        // Remap legacy key (items_remaining was the inverted column name)
        if ($key === 'items_remaining') {
            $key = 'picked_up';
            $value = ! $value;
        }

        if (! array_key_exists($key, self::ALLOWED_SETTINGS)) {
            return response()->json([
                'success' => false,
                'msg' => "Setting '$key' is not allowed.",
            ], 422);
        }

        // Validate the value against the rule for this key
        $validator = validator(
            ['value' => $value],
            ['value' => 'required|' . self::ALLOWED_SETTINGS[$key]]
        );

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'msg' => $validator->errors()->first('value'),
            ], 422);
        }

        // Unique checks for name/email/username
        if (in_array($key, ['email', 'username'])) {
            $exists = \App\Models\Users\User::where($key, $value)
                ->where('id', '!=', $user->id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'msg' => "This $key is already taken.",
                ], 422);
            }
        }

        $user->$key = $value;

        // Flag username for admin review when user changes it
        if ($key === 'username') {
            $user->username_flagged = true;
        }

        $user->save();

        return response()->json(['success' => true]);
    }

    /**
     * The user will see the previous tags on the next image
     */
    public function togglePreviousTags(Request $request): array
    {
        $user = Auth::user();
        $user->previous_tags = ! $user->previous_tags;
        $user->save();

        return ['previous_tags' => $user->previous_tags];
    }
}
