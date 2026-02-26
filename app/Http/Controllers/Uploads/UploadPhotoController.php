<?php

namespace App\Http\Controllers\Uploads;

use Geohash\GeoHash;

use App\Models\Photo;

use App\Events\ImageUploaded;
use App\Events\NewCityAdded;
use App\Events\NewCountryAdded;
use App\Events\NewStateAdded;

use App\Actions\Photos\MakeImageAction;
use App\Actions\Photos\UploadPhotoAction;
use App\Actions\Locations\ResolveLocationAction;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadPhotoRequest;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class UploadPhotoController extends Controller
{
    public function __construct(
        private MakeImageAction $makeImageAction,
        private UploadPhotoAction $uploadPhotoAction,
        private ResolveLocationAction $resolveLocationAction,
    ) {}

    /**
     * Upload a photo with GPS coordinates.
     *
     * 1. Process image & extract EXIF
     * 2. Upload to S3 (full + bbox thumbnail)
     * 3. Reverse geocode → resolve Country, State, City
     * 4. Create Photo record (FKs only, no string duplication)
     * 5. Broadcast events & new-location notifications
     *
     * No metrics, XP, or leaderboard updates happen here.
     * MetricsService::processPhoto() runs at tag verification time.
     */
    public function __invoke(UploadPhotoRequest $request): JsonResponse
    {
        $user = Auth::user();
        $file = $request->file('photo');

        // 1. Process image & extract EXIF
        $imageAndExif = $this->makeImageAction->run($file);
        $image = $imageAndExif['image'];
        $exif = $imageAndExif['exif'];
        $dateTime = getDateTimeForPhoto($exif) ?? Carbon::now();

        // 2. Upload full image + bbox thumbnail to S3
        $imageName = $this->uploadPhotoAction->run(
            $image,
            $dateTime,
            $file->hashName()
        );

        $bboxImageName = $this->uploadPhotoAction->run(
            $this->makeImageAction->run($file, true)['image'],
            $dateTime,
            $file->hashName(),
            'bbox'
        );

        // 3. Resolve location from GPS coordinates
        $coordinates = getCoordinatesFromPhoto($exif);
        $lat = $coordinates[0];
        $lon = $coordinates[1];

        $location = $this->resolveLocationAction->run($lat, $lon);

        // 4. Create Photo — FKs only, no string duplication
        $photo = Photo::create([
            'user_id' => $user->id,
            'filename' => $imageName,
            'datetime' => $dateTime,
            'remaining' => $user->items_remaining,
            'lat' => $lat,
            'lon' => $lon,
            'model' => $exif['Model'] ?? 'Unknown',
            'country_id' => $location->country->id,
            'state_id' => $location->state->id,
            'city_id' => $location->city->id,
            'platform' => 'web',
            'geohash' => (new GeoHash())->encode($lat, $lon),
            'team_id' => $user->active_team,
            'five_hundred_square_filepath' => $bboxImageName,
            'address_array' => $location->addressArray,
        ]);

        // 5. Broadcast to real-time map
        event(new ImageUploaded(
            $user,
            $photo,
            $location->country,
            $location->state,
            $location->city,
        ));

        // 6. Notify on new locations
        if ($location->country->wasRecentlyCreated) {
            event(new NewCountryAdded(
                $location->country->country,
                $location->country->shortcode,
                now()
            ));
        }

        if ($location->state->wasRecentlyCreated) {
            event(new NewStateAdded(
                $location->state->state,
                $location->country->country,
                now()
            ));
        }

        if ($location->city->wasRecentlyCreated) {
            event(new NewCityAdded(
                $location->city->city,
                $location->state->state,
                $location->country->country,
                now(),
                $location->city->id,
                $lat,
                $lon,
                $photo->id
            ));
        }

        return response()->json(['success' => true]);
    }
}
