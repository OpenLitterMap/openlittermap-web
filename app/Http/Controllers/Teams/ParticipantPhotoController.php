<?php

namespace App\Http\Controllers\Teams;

use App\Actions\Photos\DeletePhotoAction;
use App\Http\Controllers\Controller;
use App\Models\Photo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParticipantPhotoController extends Controller
{
    /**
     * List this participant's photos.
     *
     * GET /api/participant/photos
     */
    public function index(Request $request): JsonResponse
    {
        $participant = $request->attributes->get('participant');
        $team = $request->attributes->get('participant_team');

        $photos = Photo::where('team_id', $team->id)
            ->where('participant_id', $participant->id)
            ->orderByDesc('created_at')
            ->select([
                'id', 'filename', 'lat', 'lon', 'verified', 'is_public',
                'team_approved_at', 'total_tags', 'summary', 'created_at',
            ])
            ->paginate(20);

        return response()->json([
            'success' => true,
            'photos' => $photos,
        ]);
    }

    /**
     * Delete own photo (only before teacher approval).
     *
     * DELETE /api/participant/photos/{photo}
     */
    public function destroy(Request $request, Photo $photo, DeletePhotoAction $deletePhotoAction): JsonResponse
    {
        $participant = $request->attributes->get('participant');

        // Must be own photo
        if ((int) $photo->participant_id !== (int) $participant->id) {
            return response()->json([
                'success' => false,
                'message' => 'Not your photo.',
            ], 403);
        }

        // Cannot delete after teacher approval
        if ($photo->team_approved_at !== null) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete approved photos.',
            ], 422);
        }

        // Delete S3 files and soft-delete the photo
        // No metrics reversal needed — school photos not processed until approval
        $deletePhotoAction->run($photo);
        $photo->delete();

        return response()->json([
            'success' => true,
            'message' => 'Photo deleted.',
        ]);
    }
}
