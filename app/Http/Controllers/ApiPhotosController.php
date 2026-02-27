<?php

namespace App\Http\Controllers;

use App\Events\NewCityAdded;
use App\Events\NewCountryAdded;
use App\Events\NewStateAdded;
use App\Exceptions\GeocodingException;
use GeoHash;
use Carbon\Carbon;
use App\Models\Photo;

use App\Actions\Tags\ConvertV4TagsAction;

use App\Events\ImageUploaded;

use App\Actions\Photos\MakeImageAction;
use App\Actions\Photos\DeletePhotoAction;
use App\Actions\Photos\UploadPhotoAction;
use App\Actions\Locations\ResolveLocationAction;

use App\Exceptions\InvalidCoordinates;
use App\Exceptions\PhotoAlreadyUploaded;

use App\Http\Requests\Api\AddTagsRequest;
use App\Http\Requests\Api\UploadPhotoWithOrWithoutTagsRequest;
use App\Services\Metrics\MetricsService;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ApiPhotosController extends Controller
{
    protected int $userId;

    private UploadPhotoAction $uploadPhotoAction;
    private DeletePhotoAction $deletePhotoAction;
    private MakeImageAction $makeImageAction;
    private ResolveLocationAction $resolveLocationAction;

    public function __construct(
        UploadPhotoAction $uploadPhotoAction,
        DeletePhotoAction $deletePhotoAction,
        MakeImageAction $makeImageAction,
        ResolveLocationAction $resolveLocationAction
    ) {
        $this->uploadPhotoAction = $uploadPhotoAction;
        $this->deletePhotoAction = $deletePhotoAction;
        $this->makeImageAction = $makeImageAction;
        $this->resolveLocationAction = $resolveLocationAction;

        $this->middleware('auth:api');
    }

    /**
     * Stores a photo from mobile app.
     *
     * Mobile sends lat/lon/date explicitly (not from EXIF).
     * Uses ResolveLocationAction (v5) — no deprecated string columns.
     *
     * @param Request $request
     * @return Photo
     * @throws InvalidCoordinates
     * @throws GeocodingException
     * @throws GuzzleException
     */
    protected function storePhoto(Request $request): Photo
    {
        $file = $request->file('photo');
        $user = auth()->user();

        Log::channel('photos')->info([
            'app_upload' => $request->all(),
            'user_id' => $user->id,
        ]);

        $model = $request->filled('model')
            ? $request->model
            : 'Mobile app v2';

        $image = $this->makeImageAction->run($file)['image'];

        $lat = $request['lat'];
        $lon = $request['lon'];

        if ($lat === null || $lon === null) {
            Log::info("null coordinates found for userId $user->id");
            throw new InvalidCoordinates();
        }

        // Note: 0,0 coordinates are accepted. Future feature: manual coordinate assignment.

        $date = str_contains($request['date'], ':')
            ? $request['date']
            : (int) $request['date'];

        $date = Carbon::parse($date);

        // Upload images to both 's3' and 'bbox' disks
        $imageName = $this->uploadPhotoAction->run(
            $image,
            $date,
            $file->hashName()
        );

        $bboxImageName = $this->uploadPhotoAction->run(
            $this->makeImageAction->run($file, true)['image'],
            $date,
            $file->hashName(),
            'bbox'
        );

        // v5: Resolve locations via ResolveLocationAction
        $location = $this->resolveLocationAction->run($lat, $lon);

        $pickedUp = (isset($request->picked_up) && ! is_null($request->picked_up))
            ? $request->picked_up
            : ! $user->items_remaining;

        // v5: FKs only, no deprecated string columns
        $photo = $user->photos()->create([
            'filename' => $imageName,
            'datetime' => $date,
            'lat' => $lat,
            'lon' => $lon,
            'country_id' => $location->country->id,
            'state_id' => $location->state->id,
            'city_id' => $location->city->id,
            'model' => $model,
            'remaining' => ! $pickedUp,
            'platform' => 'mobile',
            'geohash' => GeoHash::encode($lat, $lon),
            'team_id' => $user->active_team,
            'five_hundred_square_filepath' => $bboxImageName,
            'address_array' => $location->addressArray,
        ]);

        // Broadcast to real-time map
        event(new ImageUploaded(
            $user,
            $photo,
            $location->country,
            $location->state,
            $location->city,
        ));

        // Notify on new locations
        if ($location->country->wasRecentlyCreated) {
            event(new NewCountryAdded($location->country->country, $location->country->shortcode, now()));
        }

        if ($location->state->wasRecentlyCreated) {
            event(new NewStateAdded($location->state->state, $location->country->country, now()));
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

        return $photo;
    }

    /**
     * Upload Photo (mobile v1)
     */
    public function store(Request $request): array
    {
        $request->validate([
            'photo' => 'required|mimes:jpg,png,jpeg,heic,heif',
            'lat' => 'required|numeric',
            'lon' => 'required|numeric',
            'date' => 'required',
        ]);

        $file = $request->file('photo');

        if ($file->getError() === 3) {
            return [
                'success' => false,
                'msg' => 'error-3',
            ];
        }

        try {
            $photo = $this->storePhoto($request);
        } catch (PhotoAlreadyUploaded|InvalidCoordinates $e) {
            return [
                'success' => false,
                'msg' => $e->getMessage(),
            ];
        }

        return [
            'success' => true,
            'photo_id' => $photo->id,
        ];
    }

    /**
     * Upload Photo with or without tags (mobile v2)
     */
    public function uploadWithOrWithoutTags(UploadPhotoWithOrWithoutTagsRequest $request): array
    {
        try {
            $photo = $this->storePhoto($request);
        } catch (PhotoAlreadyUploaded $e) {
            Log::info('ApiPhotosController@uploadWithOrWithoutTags.1', [$e->getMessage()]);

            return [
                'success' => false,
                'msg' => 'photo-already-uploaded',
            ];
        } catch (InvalidCoordinates $e) {
            Log::info('ApiPhotosController@uploadWithOrWithoutTags.2', [$e->getMessage()]);

            return [
                'success' => false,
                'msg' => 'invalid-coordinates',
            ];
        }

        // Convert old v4 tags to v5 PhotoTags via migration pipeline
        if ($request->tags || $request->custom_tags) {
            $v4Tags = $request->tags ?? [];
            if (is_string($v4Tags)) {
                $v4Tags = json_decode($v4Tags, true) ?? [];
            }

            $customTags = $request->custom_tags ?? [];
            if (is_string($customTags)) {
                $customTags = json_decode($customTags, true) ?? [];
            }

            app(ConvertV4TagsAction::class)->run(
                auth()->id(),
                $photo->id,
                $v4Tags,
                ! $photo->remaining,
                $customTags
            );
        }

        return [
            'success' => true,
            'photo_id' => $photo->id,
        ];
    }

    /**
     * Check if the user has any available photos that are uploaded but not tagged
     */
    public function check()
    {
        $user = auth()->user();

        $photos = $user->photos()
            ->where('verified', 0)
            ->select('id', 'filename')
            ->get();

        return ['photos' => $photos];
    }

    /**
     * Delete a photo — reverses metrics, removes S3 files, soft-deletes the row.
     */
    public function deleteImage(Request $request)
    {
        $user = auth()->user();

        $photo = Photo::where([
            'id' => $request->photoId,
            'user_id' => $user->id,
        ])->first();

        if (! $photo) {
            return response()->json([
                'success' => false,
                'msg' => 'Photo not found',
            ], 403);
        }

        // Capture XP before MetricsService clears it
        $photoXp = (int) ($photo->processed_xp ?? 0);

        // Reverse metrics before soft delete (if photo was processed)
        if ($photo->processed_at !== null) {
            app(MetricsService::class)->deletePhoto($photo);
        }

        // Delete S3 files
        $this->deletePhotoAction->run($photo);

        // Soft delete
        $photo->delete();

        $user->xp = max(0, $user->xp - $photoXp);
        $user->total_images = max(0, $user->total_images - 1);
        $user->save();

        return response()->json(['success' => true]);
    }
}
