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
use App\Enums\XpScore;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadPhotoRequest;

use App\Services\Metrics\MetricsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class UploadPhotoController extends Controller
{
    public function __construct(
        private MakeImageAction $makeImageAction,
        private UploadPhotoAction $uploadPhotoAction,
        private ResolveLocationAction $resolveLocationAction,
        private MetricsService $metricsService,
    ) {}

    /**
     * Upload a photo with GPS coordinates.
     *
     * Supports two modes:
     * - Web: extracts lat/lon/date from EXIF (default)
     * - Mobile: accepts explicit lat, lon, date fields (overrides EXIF)
     *
     * Awards XpScore::Upload (5) XP per upload to MySQL users.xp.
     * Redis XP is handled by MetricsService when tags are processed
     * (XpCalculator includes upload base in photo.xp — no Redis push here to avoid double-counting).
     */
    public function __invoke(UploadPhotoRequest $request): JsonResponse
    {
        $user = Auth::user();
        $file = $request->file('photo');
        $hasExplicit = $request->hasExplicitCoordinates();

        // 1. Process image & extract EXIF
        $imageAndExif = $this->makeImageAction->run($file);
        $image = $imageAndExif['image'];
        $exif = $imageAndExif['exif'];

        // 2. Resolve coordinates and datetime
        if ($hasExplicit) {
            // Mobile: explicit lat/lon/date from request
            $lat = (float) $request->input('lat');
            $lon = (float) $request->input('lon');

            $dateInput = $request->input('date');
            $dateTime = is_numeric($dateInput)
                ? Carbon::createFromTimestamp((int) $dateInput)
                : Carbon::parse($dateInput);
        } else {
            // Web: extract from EXIF
            $dateTime = getDateTimeForPhoto($exif) ?? Carbon::now();
            $coordinates = getCoordinatesFromPhoto($exif);
            $lat = $coordinates[0];
            $lon = $coordinates[1];
        }

        // 3. Upload full image + bbox thumbnail to S3
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

        // 4. Resolve location from GPS coordinates
        $location = $this->resolveLocationAction->run($lat, $lon);

        // 5. Determine remaining/picked_up and device model
        // At upload time: explicit request value wins, then user default, then true (remaining)
        if ($request->has('picked_up')) {
            $remaining = ! $request->boolean('picked_up');
        } elseif ($user->picked_up !== null) {
            $remaining = ! $user->picked_up;
        } else {
            $remaining = true; // null preference → default to remaining (unknown)
        }

        $deviceModel = $request->input('model', $exif['Model'] ?? 'Unknown');

        // 6. Create Photo — FKs only, no string duplication
        $photo = Photo::create([
            'user_id' => $user->id,
            'filename' => $imageName,
            'datetime' => $dateTime,
            'remaining' => $remaining,
            'lat' => $lat,
            'lon' => $lon,
            'model' => $deviceModel,
            'country_id' => $location->country->id,
            'state_id' => $location->state->id,
            'city_id' => $location->city->id,
            'platform' => $hasExplicit ? 'mobile' : 'web',
            'geohash' => (new GeoHash())->encode($lat, $lon),
            'team_id' => $request->attributes->get('participant_team')?->id ?? $user->active_team,
            'participant_id' => $request->attributes->get('participant')?->id,
            'five_hundred_square_filepath' => $bboxImageName,
            'address_array' => $location->addressArray,
        ]);

        // 7. Broadcast to real-time map
        event(new ImageUploaded(
            $user,
            $photo,
            $location->country,
            $location->state,
            $location->city,
        ));

        // 8. Notify on new locations
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

        // 9. Award upload XP to MySQL (immediate feedback for profile)
        // and metrics table (so user appears on time-filtered leaderboards)
        $uploadXp = XpScore::Upload->xp();
        $user->increment('xp', $uploadXp);
        $this->metricsService->recordUploadMetrics($photo, $uploadXp);

        return response()->json([
            'success' => true,
            'photo_id' => $photo->id,
            'lat' => $photo->lat,
            'lon' => $photo->lon,
            'city' => $location->city->city,
            'state' => $location->state->state,
            'country' => $location->country->country,
            'display_name' => $location->displayName,
            'xp_awarded' => $uploadXp,
            'user_xp_total' => $user->xp,
        ]);
    }
}
