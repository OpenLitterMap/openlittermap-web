<?php

namespace App\Http\Controllers\Admin;

use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Photo;
use App\Services\Metrics\MetricsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class AdminResetTagsController extends Controller
{
    public function __construct(
        private MetricsService $metricsService,
    ) {
        $this->middleware('admin');
    }

    /**
     * Reset all tags on a photo — reverses metrics, deletes tags, resets to unverified.
     *
     * Only works on photos not yet admin-approved (superadmins can override).
     */
    public function __invoke(Request $request)
    {
        $photo = Photo::findOrFail($request->photoId);

        if ($photo->verified->value >= VerificationStatus::ADMIN_APPROVED->value) {
            return ['success' => true];
        }

        // Reverse metrics before clearing tags (if photo was processed)
        if ($photo->processed_at !== null) {
            $this->metricsService->deletePhoto($photo);
        }

        // Delete v5 photo tags
        PhotoTag::where('photo_id', $photo->id)->delete();

        // Reset photo state
        $photo->verified = VerificationStatus::UNVERIFIED->value;
        $photo->summary = null;
        $photo->xp = 0;
        $photo->total_tags = 0;
        $photo->save();

        logAdminAction($photo, Route::getCurrentRoute()->getActionMethod());
        rewardXpToAdmin();

        return ['success' => true];
    }
}
