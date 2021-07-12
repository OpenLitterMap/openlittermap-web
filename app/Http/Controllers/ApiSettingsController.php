<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Info(
 *     description="Api Settings Controller",
 *     title="Api Settings Controller",
 *     version="1.0.0"
 * )
 */
class ApiSettingsController extends Controller
{
    /**
     * @OA\Post(path="/api/settings/privacy/maps/name",
     *   operationId="mapsName",
     *   summary="Toggle privacy of users name on the maps",
     *   @OA\Response(response=200,
     *     description="the toggled show_name_maps privacy setting",
     *     @OA\JsonContent()
     *   ),
     *   @OA\Response(response=401,
     *     description="Unauthenticated",
     *     @OA\JsonContent()
     *   )
     * )
     */
    public function mapsName(Request $request)
    {
        $user = Auth::guard('api')->user();
        $user->show_name_maps = !$user->show_name_maps;
        $user->save();
        return ['show_name_maps' => $user->show_name_maps];
    }

    /**
     * @OA\Post(path="/api/settings/privacy/maps/username",
     *   operationId="mapsUsername",
     *   summary="Toggle privacy of users username on the maps",
     *   @OA\Response(response=200,
     *     description="the toggled show_username_maps privacy setting",
     *     @OA\JsonContent()
     *   ),
     *   @OA\Response(response=401,
     *     description="Unauthenticated",
     *     @OA\JsonContent()
     *   )
     * )
     */
    public function mapsUsername(Request $request)
    {
        $user = Auth::guard('api')->user();
        $user->show_username_maps = !$user->show_username_maps;
        $user->save();
        return ['show_username_maps' => $user->show_username_maps];
    }

    /**
     * @OA\Post(path="/api/settings/privacy/leaderboard/name",
     *   operationId="leaderboardName",
     *   summary="Toggle privacy of users name on the leaderboards",
     *   @OA\Response(response=200,
     *     description="the toggled show_name privacy setting",
     *     @OA\JsonContent()
     *   ),
     *   @OA\Response(response=401,
     *     description="Unauthenticated",
     *     @OA\JsonContent()
     *   )
     * )
     */
    public function leaderboardName(Request $request)
    {
        $user = Auth::guard('api')->user();
        $user->show_name = !$user->show_name;
        $user->save();
        return ['show_name' => $user->show_name];
    }

    /**
     * @OA\Post(path="/api/settings/privacy/leaderboard/username",
     *   operationId="leaderboardUsername",
     *   summary="Toggle privacy of users username on the leaderboards",
     *   @OA\Response(response=200,
     *     description="the toggled show_username privacy setting",
     *     @OA\JsonContent()
     *   ),
     *   @OA\Response(response=401,
     *     description="Unauthenticated",
     *     @OA\JsonContent()
     *   )
     * )
     */
    public function leaderboardUsername(Request $request)
    {
        $user = Auth::guard('api')->user();
        $user->show_username = !$user->show_username;
        $user->save();
        return ['show_username' => $user->show_username];
    }

    /**
     * @OA\Post(path="/api/settings/privacy/createdby/name",
     *   operationId="createdByName",
     *   summary="Toggle privacy of users name on the createdBy section of a location",
     *   @OA\Response(response=200,
     *     description="the toggled show_name_createdby privacy setting",
     *     @OA\JsonContent()
     *   ),
     *   @OA\Response(response=401,
     *     description="Unauthenticated",
     *     @OA\JsonContent()
     *   )
     * )
     */
    public function createdByName(Request $request)
    {
        $user = Auth::guard('api')->user();
        $user->show_name_createdby = !$user->show_name_createdby;
        $user->save();
        return ['show_name_createdby' => $user->show_name_createdby];
    }

    /**
     * @OA\Post(path="/api/settings/privacy/createdby/username",
     *   operationId="createdByUsername",
     *   summary="Toggle privacy of users username on the createdBy section of a location",
     *   @OA\Response(response=200,
     *     description="the toggled show_username_createdby privacy setting",
     *     @OA\JsonContent()
     *   ),
     *   @OA\Response(response=401,
     *     description="Unauthenticated",
     *     @OA\JsonContent()
     *   )
     * )
     */
    public function createdByUsername(Request $request)
    {
        $user = Auth::guard('api')->user();
        $user->show_username_createdby = !$user->show_username_createdby;
        $user->save();
        return ['show_username_createdby' => $user->show_username_createdby];
    }

    /**
     * @OA\Post(path="/api/settings/update/{type}",
     *   operationId="update",
     *   summary="Update a setting",
     *   @OA\Parameter(
     *     name="type",
     *     description="Setting to update",
     *     in="path",
     *     required=true,
     *     schema={
     *       "type": "string",
     *       "enum": {"Name", "Username", "Email"},
     *       "default": "Email"
     *     }
     *   ),
     *   @OA\Response(response=200,
     *     description="the toggled name privacy setting",
     *     @OA\JsonContent()
     *   ),
     *   @OA\Response(response=401,
     *     description="Unauthenticated",
     *     @OA\JsonContent()
     *   )
     * )
     */
    public function update(Request $request, $type)
    {
        $user = Auth::guard('api')->user();

        if ($type == "Name") {
            $user->name = $request->Name;
            $user->save();

            return ['name' => $user->name];

        } else if ($type == "Username") {
            $user->username = $request->Username;
            $user->save();

            return ['username' => $user->username];

        } else {
            $user->email = $request->Email;
            $user->save();

            // todo - send email to confirm updated email

            return ['email' => $user->email];
        }

        return ['error' => 'Wrong key?'];
    }

    /**
     * @OA\Post(path="/api/settings/privacy/toggle-previous-tags",
     *   operationId="togglePreviousTags",
     *   summary="The user will see the previous tags on the next image",
     *   @OA\Response(response=200,
     *     description="the toggled previous_tags privacy setting",
     *     @OA\JsonContent()
     *   ),
     *   @OA\Response(response=401,
     *     description="Unauthenticated",
     *     @OA\JsonContent()
     *   )
     * )
     */
    public function togglePreviousTags(Request $request)
    {
        $user = Auth::guard('api')->user();

        $user->previous_tags = !$user->previous_tags;

        $user->save();

        return ['previous_tags' => $user->previous_tags];
    }
}
