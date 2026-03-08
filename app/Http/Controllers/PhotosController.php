<?php

namespace App\Http\Controllers;

use App\Actions\Photos\DeletePhotoAction;
use App\Models\Photo;
use App\Services\Metrics\MetricsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PhotosController extends Controller
{
    public function __construct(
        private DeletePhotoAction $deletePhotoAction,
    ) {
        // Auth handled by route middleware (auth:sanctum)
    }

    /**
     * Delete a photo — reverses metrics, removes S3 files, soft-deletes the row.
     */
    public function deleteImage(Request $request)
    {
        $user = Auth::user();
        $photo = Photo::findOrFail($request->photoid);

        if ($user->id !== $photo->user_id) {
            abort(403);
        }

        // Reverse metrics before soft delete (if photo was processed)
        // MetricsService::deletePhoto() reverses both upload XP and tag XP
        // from MySQL metrics, Redis, and users.xp
        if ($photo->processed_at !== null) {
            app(MetricsService::class)->deletePhoto($photo);
        }

        // Delete S3 files
        $this->deletePhotoAction->run($photo);

        // Soft delete
        $photo->delete();

        return response()->json(['message' => 'Photo deleted successfully!']);
    }

}
