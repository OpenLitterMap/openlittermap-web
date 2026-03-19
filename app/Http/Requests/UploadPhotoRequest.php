<?php

namespace App\Http\Requests;

use App\Models\Photo;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UploadPhotoRequest extends FormRequest
{
    public $stopOnFirstFailure = true;

    protected string $gpsError1 = "Sorry, no GPS on this one.";

    protected string $gpsError2 = "Error: Could not read GPS coordinates from this image.
        You may have lost the geotags when transferring images across devices
        or you might need to enable another setting to make them available.";

    public function rules(): array
    {
        return [
            'photo' => [
                'required',
                'image',
                'mimes:jpg,png,jpeg,heif,heic,webp',
                'dimensions:min_width=1,min_height=1',
                'max:20480'
            ],
            'lat' => ['sometimes', 'numeric', 'between:-90,90'],
            'lon' => ['sometimes', 'numeric', 'between:-180,180'],
            'date' => ['sometimes'],
            'picked_up' => ['sometimes', 'boolean'],
            'is_public' => ['sometimes', 'boolean'],
            'model' => ['sometimes', 'string', 'max:255'],
        ];
    }

    /**
     * Whether explicit coordinates are provided (mobile upload).
     */
    public function hasExplicitCoordinates(): bool
    {
        return $this->has('lat') && $this->has('lon') && $this->has('date');
    }

    public function failedValidation(Validator|\Illuminate\Contracts\Validation\Validator $validator)
    {
        $firstMessage = $validator->errors()->first();
        $errorCode = $this->resolveErrorCode($firstMessage);

        throw new HttpResponseException(response()->json([
            'success' => false,
            'error' => $errorCode,
            'message' => $firstMessage,
            'errors' => $validator->errors()->toArray(),
        ], 422));
    }

    /**
     * Map validation messages to typed error codes for the frontend.
     */
    private function resolveErrorCode(string $message): string
    {
        if (str_contains($message, 'EXIF')) return 'no_exif';
        if (str_contains($message, 'GPS') || str_contains($message, 'no GPS')) return 'no_gps';
        if (str_contains($message, 'date')) return 'no_datetime';
        if (str_contains($message, 'already uploaded')) return 'duplicate';
        if (str_contains($message, 'coordinates')) return 'invalid_coordinates';

        return 'validation_error';
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $photo = $this->file('photo');

                if (!$photo || !$photo->isValid()) {
                    return;
                }

                $hasExplicit = $this->hasExplicitCoordinates();

                // Reject (0, 0) — Null Island is not a real litter location
                if ($hasExplicit && (float) $this->input('lat') == 0 && (float) $this->input('lon') == 0) {
                    $validator->errors()->add('lat', 'Invalid coordinates: (0, 0) is not accepted.');
                    return;
                }

                // When explicit coords are provided, EXIF is optional (mobile may strip it)
                if ($hasExplicit) {
                    // Parse the explicit date for duplicate check
                    $dateInput = $this->input('date');
                    $dateTime = is_numeric($dateInput)
                        ? \Carbon\Carbon::createFromTimestamp((int) $dateInput)
                        : \Carbon\Carbon::parse($dateInput);

                    if (! $this->attributes->get('participant')) {
                        $photoExists = Photo::where([
                            'user_id' => auth()->id(),
                            'datetime' => $dateTime,
                        ])->exists();

                        if ($photoExists) {
                            $validator->errors()->add('photo', 'You have already uploaded this photo');
                        }
                    }

                    return;
                }

                // --- EXIF-based validation (web uploads) ---

                try {
                    $exif = @exif_read_data($photo->getRealPath()) ?: [];
                } catch (\Exception $e) {
                    $validator->errors()->add('photo', 'Could not read EXIF data from the image.');
                    return;
                }

                if (empty($exif)) {
                    $validator->errors()->add('photo', 'The uploaded image does not contain EXIF data.');
                    return;
                }

                // DateTime validation
                $dateTime = getDateTimeForPhoto($exif);

                if (!$dateTime) {
                    $validator->errors()->add('photo', 'The image does not contain a date. Please check your camera settings.');
                    return;
                }

                // Duplicate photo check (skip for participant sessions —
                // different students may share the same EXIF datetime)
                if (! $this->attributes->get('participant')) {
                    $photoExists = Photo::where([
                        'user_id' => auth()->id(),
                        'datetime' => $dateTime,
                    ])->exists();

                    if ($photoExists) {
                        $validator->errors()->add('photo', 'You have already uploaded this photo');
                        return;
                    }
                }

                // GPS validation
                $hasGps =
                    !empty($exif["GPSLatitudeRef"]) &&
                    !empty($exif["GPSLatitude"]) &&
                    !empty($exif["GPSLongitudeRef"]) &&
                    !empty($exif["GPSLongitude"]);

                if (!$hasGps) {
                    $validator->errors()->add('photo', $this->gpsError1);
                    return;
                }

                // Convert GPS to coordinates (dmsToDec returns null on malformed data)
                $coordinates = getCoordinatesFromPhoto($exif);
                $lat = $coordinates[0] ?? null;
                $lon = $coordinates[1] ?? null;

                if ($lat === null || $lon === null) {
                    $validator->errors()->add('photo', $this->gpsError2);
                }
            }
        ];
    }
}
