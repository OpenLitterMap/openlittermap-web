<?php

namespace App\Http\Controllers\Photos;

use App\Models\Photo;
use App\Http\Controllers\Controller;
use App\Actions\Photos\GetPreviousCustomTagsAction;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class GetUsersUntaggedPhotosController extends Controller
{
    /**
     * Get unverified photos for tagging
     */
    public function index (GetPreviousCustomTagsAction $previousTagsAction): JsonResponse
    {
        $user = Auth::user();

        $query = Photo::where([
            'user_id' => $user->id,
            'verified' => 0,
            'verification' => 0
        ]);

        // we need to get this before the pagination
        $remaining = $query->count();

        $photos = $query
            ->with('team')
            ->select('id', 'filename', 'lat', 'lon', 'model', 'remaining', 'display_name', 'datetime', 'team_id')
            ->orderBy('id', 'asc')
            ->paginate(1);

        // We should move this into the GET TAGS request after refactoring custom tags.
        $customTags = (request('page') === '1')
            ? $previousTagsAction->run($user)
            : null;

        return response()->json([
            'photos' => $photos,
            'remaining' => $remaining,
            'custom_tags' => $customTags
        ]);
    }
}
