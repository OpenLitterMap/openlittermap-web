<?php

namespace App\Http\Controllers\User;

use App\Actions\Photos\DeletePhotoAction;
use App\Actions\Photos\GetPreviousCustomTagsAction;
use App\Enums\VerificationStatus;
use App\Models\Photo;
use App\Services\Metrics\MetricsService;
use App\Traits\Photos\FilterPhotos;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class UserPhotoController extends Controller
{
    protected $paginate = 300;

    use FilterPhotos;

    /**
     * @deprecated No frontend consumer. Use POST /api/v3/tags for individual photo tagging.
     */
    public function bulkTag(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Use POST /api/v3/tags for tagging'], 410);
    }

    /**
     * Bulk delete user's own photos.
     *
     * Reverses metrics, removes S3 files, soft-deletes each photo,
     * and decrements user counters.
     */
    public function destroy(Request $request): array
    {
        $user = Auth::user();
        $metricsService = app(MetricsService::class);
        $deletePhotoAction = app(DeletePhotoAction::class);

        $ids = ($request->selectAll) ? $request->exclIds : $request->inclIds;

        $photos = $this->filterPhotos(json_encode($request->filters), $request->selectAll, $ids)->get();

        $deleted = 0;
        $totalXpToRemove = 0;

        foreach ($photos as $photo) {
            try {
                if ($user->id !== $photo->user_id) {
                    continue;
                }

                // Capture XP before MetricsService clears it
                $totalXpToRemove += (int) ($photo->processed_xp ?? 0);

                // Reverse metrics before soft delete (if photo was processed)
                if ($photo->processed_at !== null) {
                    $metricsService->deletePhoto($photo);
                }

                // Delete S3 files
                $deletePhotoAction->run($photo);

                // Soft delete
                $photo->delete();

                $deleted++;
            } catch (\Exception $e) {
                Log::info(["Photo could not be deleted", $e->getMessage()]);
            }
        }

        // Decrement user counters
        if ($deleted > 0) {
            $user->xp = max(0, $user->xp - $totalXpToRemove);
            $user->total_images = max(0, $user->total_images - $deleted);
            $user->save();
        }

        return ['success' => true];
    }

    /**
     * Return filtered array of the users photos
     *
     * @return JsonResponse
     */
    public function filter (): JsonResponse
    {
        $query = $this->filterPhotos(request()->filters);

        $count = $query->count();
        $paginate = $query->simplePaginate($this->paginate);

        return response()->json([
            'count' => $count,
            'paginate' => $paginate
        ]);
    }

    /**
     * Return non-filtered array of the users photos
     *
     * @return array
     */
    public function index ()
    {
        $query = Photo::select('id', 'filename', 'verified', 'datetime', 'created_at')
            ->where('user_id', auth()->user()->id)
            ->where('verified', VerificationStatus::UNVERIFIED->value);

        return [
            'paginate' => $query->simplePaginate($this->paginate),
            'count' => $query->count()
        ];
    }

    /**
     * List of the user's previously added custom tags
     */
    public function previousCustomTags (GetPreviousCustomTagsAction $previousTagsAction)
    {
        return $previousTagsAction->run(request()->user());
    }
}
