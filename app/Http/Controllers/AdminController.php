<?php

namespace App\Http\Controllers;

use App\Actions\Tags\AddTagsToPhotoAction;
use App\Enums\VerificationStatus;
use App\Events\TagsVerifiedByAdmin;
use App\Models\Littercoin;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Photo;
use App\Models\Users\User;
use App\Services\Metrics\MetricsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

class AdminController extends Controller
{
    /**
     * Apply IsAdmin middleware to all of these routes
     */
    public function __construct(
        private MetricsService $metricsService,
        private AddTagsToPhotoAction $addTagsAction,
    ) {
        $this->middleware('admin');
    }

    /**
     * Get the total number of users who have signed up
     */
    public function getUserCount()
    {
        $users = User::where('verified', 1)
            ->orWhere('name', 'default')
            ->get()
            ->sortBy('created_at');

        $totalUsers = $users->count();

        $users = $users->groupBy(function ($val) {
            return Carbon::parse($val->created_at)->format('m-y');
        });

        $upm = [];
        $months = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        foreach ($users as $index => $monthlyUser) {
            $month = $months[(int) substr($index, 0, 2)];
            $year = substr($index, 2, 5);
            $upm[$month . $year] = $monthlyUser->count();
        }
        $upm = json_encode($upm);

        $usersUploaded = User::where('has_uploaded', 1)->get();

        $usersUploaded = $usersUploaded->groupBy(function ($val) {
            return Carbon::parse($val->created_at)->format('m-y');
        });

        $uupm = [];
        foreach ($usersUploaded as $index => $userUploaded) {
            $month = $months[(int) $substr = substr($index, 0, 2)];
            $year = substr($index, 2, 5);
            $uupm[$month . $year] = $userUploaded->count();
        }
        $uupm = json_encode($uupm);

        return view('admin.usercount', compact('users', 'totalUsers', 'upm', 'uupm'));
    }

    /**
     * Approve a photo — sets verified to ADMIN_APPROVED, fires metrics event.
     *
     * Idempotent: atomic WHERE prevents double-processing.
     * Photos must have tags (summary not null) before approval.
     * S3 images are NOT deleted — photos remain viewable after approval.
     */
    public function verify(Request $request): JsonResponse
    {
        $photo = Photo::findOrFail($request->photoId);

        // Precondition: photo must have tags
        if ($photo->summary === null) {
            return response()->json([
                'success' => false,
                'message' => 'Photo has no tags to approve.',
            ], 422);
        }

        // Atomic update — only affects photos not yet admin-approved and public
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

            rewardXpToAdmin();
        }

        return response()->json([
            'success' => true,
            'approved' => $affected > 0,
        ]);
    }

    /**
     * Delete a photo — reverses metrics, soft-deletes the row.
     *
     * MetricsService::deletePhoto() runs BEFORE the soft delete.
     * If metrics reversal fails, the delete is aborted.
     */
    public function destroy(Request $request): JsonResponse
    {
        $photo = Photo::findOrFail($request->photoId);

        // Reverse metrics before soft delete (if photo was processed)
        if ($photo->processed_at !== null) {
            $this->metricsService->deletePhoto($photo);
        }

        logAdminAction($photo, Route::getCurrentRoute()->getActionMethod());

        // Detach Littercoin if linked
        $littercoin = Littercoin::where('photo_id', $photo->id)->first();

        if ($littercoin) {
            $littercoin->photo_id = null;
            $littercoin->save();
        }

        // Soft delete (SoftDeletes trait)
        $photo->delete();

        rewardXpToAdmin();

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * Edit tags on a photo, then approve it.
     *
     * Replaces all existing PhotoTags with the new tag set inside a transaction.
     * Uses AddTagsToPhotoAction to create v5 PhotoTag records + generate summary + XP.
     * Then sets verified = ADMIN_APPROVED and fires metrics event.
     */
    public function updateDelete(Request $request): JsonResponse
    {
        $photo = Photo::findOrFail($request->photoId);

        // Replace tags inside a transaction
        DB::transaction(function () use ($request, $photo) {
            // Delete existing tags
            PhotoTag::where('photo_id', $photo->id)->delete();

            // Create new tags via v5 action (generates summary + XP)
            // skipVerification: admin controller handles approval + metrics itself
            $this->addTagsAction->run(
                $photo->user_id,
                $photo->id,
                $request->tags ?? [],
                skipVerification: true
            );
        });

        $photo->refresh();

        // Atomic approve — same pattern as verify()
        $affected = Photo::where('id', $photo->id)
            ->where('is_public', true)
            ->where('verified', '<', VerificationStatus::ADMIN_APPROVED->value)
            ->update(['verified' => VerificationStatus::ADMIN_APPROVED->value]);

        logAdminAction($photo, Route::getCurrentRoute()->getActionMethod());

        if ($affected > 0) {
            // First-time approval — fire event for metrics + Littercoin
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
            // Re-tag of already-approved photo — update metrics directly
            // (no Littercoin reward on re-tag, only on first approval)
            $this->metricsService->processPhoto($photo);
        }

        rewardXpToAdmin();

        return response()->json([
            'success' => true,
            'approved' => $affected > 0,
            'photo' => $photo->fresh(),
        ]);
    }

    /**
     * Returns all the countries that have unverified photos
     * and their totals
     */
    public function getCountriesWithPhotos(): Collection
    {
        $totalsQuery = Photo::query()
            ->selectRaw('country_id, count(*) as total')
            ->where('verified', '<', VerificationStatus::ADMIN_APPROVED->value)
            ->where('is_public', true)
            ->whereNotNull('summary')
            ->groupBy('country_id');

        // Using DB to avoid extra appended properties
        return DB::table('countries')
            ->selectRaw('id, country, q.total')
            ->rightJoinSub($totalsQuery, 'q', 'countries.id', '=', 'q.country_id')
            ->get()
            ->keyBy('id');
    }
}
