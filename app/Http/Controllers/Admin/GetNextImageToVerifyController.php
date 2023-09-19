<?php

namespace App\Http\Controllers\Admin;

use App\Models\Photo;
use App\Http\Requests\GetImageForVerificationRequest;

use App\Http\Controllers\Controller;

use Illuminate\Support\Facades\Redis;
use Illuminate\Database\Eloquent\Builder;

class GetNextImageToVerifyController extends Controller
{
    /**
     * Get the next image to verify
     *
     * @param GetImageForVerificationRequest $request
     * @return array
     */
    public function __invoke (GetImageForVerificationRequest $request): array
    {
        // Photos that are uploaded and tagged come first
        /** @var Photo $photo */
        $photo = $this->filterPhotos()
            ->when($request->skip, function ($q) use ($request) {
                $q->skip((int) $request->skip);
            })
            ->where('verification', 0.1)
            ->first();

        if (!$photo)
        {
            // Photos that have been uploaded, but not tagged or submitted for verification
            /** @var Photo $photo */
            $photo = $this->filterPhotos()
                ->when($request->skip, function ($q) use ($request) {
                    $q->skip($request->skip);
                })
                ->where('verification', 0)
                ->first();
        }

        if (!$photo)
        {
            return [
                'success' => false,
                'msg' => 'photo not found'
            ];
        }

        // Load the tags for the photo
        $photo->tags();

        // Count photos that are uploaded but not tagged
        $photosNotProcessed = $this->filterPhotos()
            ->where('verification', 0)
            ->count();

        // Count photos that are uploaded but not tagged
        $photosNotProcessedForAdminTagging = $this->filterPhotos()
            ->whereHas('user', function ($q) {
                return $q->where('enable_admin_tagging', true);
            })
            ->where('verification', 0)
            ->count();

        // Count photos submitted for verification
        $photosAwaitingVerification = $this->filterPhotos()
            ->where([
                ['verified', '<', 2], // not verified
                ['verification', '>', 0], // submitted for verification
            ])
            ->count();

        $userVerificationCount = false;

        if (Redis::hexists("user_verification_count", $photo->user_id))
        {
            $userVerificationCount = Redis::hget("user_verification_count", $photo->user_id);
        }

        return [
            'photo' => $photo,
            'photosNotProcessed' => $photosNotProcessed,
            'photosAwaitingVerification' => $photosAwaitingVerification,
            'userVerificationCount' => $userVerificationCount,
            'photosNotProcessedForAdminTagging' => $photosNotProcessedForAdminTagging
        ];
    }

    /**
     * Generates a query builder with filtered photos
     * @return Builder|mixed
     */
    private function filterPhotos(): Builder
    {
        return Photo::onlyFromUsersThatAllowTagging()
            ->whereHas('user', function ($q) {
                return $q->where('verification_required', true);
            })
            ->with(['user' => function ($q) {
                $q->select('id', 'username', 'verification_required');
            }])
            ->with('customTags')
            ->when(request('country_id'), function (Builder $q) {
                return $q->whereCountryId(request('country_id'));
            });
    }
}
