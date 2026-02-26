<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Tags\AddTagsToPhotoAction;
use App\Enums\VerificationStatus;
use App\Events\TagsVerifiedByAdmin;
use App\Http\Controllers\Controller;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Photo;
use App\Services\Metrics\MetricsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * @deprecated Use AdminController::updateDelete() instead.
 */
class UpdateTagsController extends Controller
{
    public function __construct(
        private MetricsService $metricsService,
        private AddTagsToPhotoAction $addTagsAction,
    ) {
        $this->middleware('admin');
    }

    /**
     * Update tags on a photo and approve it.
     *
     * Mirrors AdminController::updateDelete() logic.
     */
    public function __invoke(Request $request)
    {
        $photo = Photo::findOrFail($request->photoId);

        // Replace tags inside a transaction
        DB::transaction(function () use ($request, $photo) {
            PhotoTag::where('photo_id', $photo->id)->delete();

            $this->addTagsAction->run(
                $photo->user_id,
                $photo->id,
                $request->tags ?? []
            );
        });

        $photo->refresh();

        // Atomic approve
        $affected = Photo::where('id', $photo->id)
            ->where('is_public', true)
            ->where('verified', '<', VerificationStatus::ADMIN_APPROVED->value)
            ->update(['verified' => VerificationStatus::ADMIN_APPROVED->value]);

        logAdminAction($photo, Route::getCurrentRoute()->getActionMethod());

        if ($affected > 0) {
            $photo->refresh();

            event(new TagsVerifiedByAdmin(
                $photo->id,
                $photo->user_id,
                $photo->country_id,
                $photo->state_id,
                $photo->city_id,
                $photo->team_id,
            ));
        } elseif ($photo->is_public && $photo->processed_at !== null) {
            // Re-tag of already-approved photo
            $this->metricsService->processPhoto($photo);
        }

        rewardXpToAdmin();

        return ['success' => true];
    }
}
