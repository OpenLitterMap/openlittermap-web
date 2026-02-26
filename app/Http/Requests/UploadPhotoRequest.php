<?php

namespace App\Http\Requests;

use App\Models\Photo;
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
                'mimes:jpg,png,jpeg,heif,heic',
                'dimensions:min_width=1,min_height=1',
                'max:20480'
            ]
        ];
    }

    public function failedValidation(Validator|\Illuminate\Contracts\Validation\Validator $validator)
    {
        throw new \Illuminate\Validation\ValidationException($validator);
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $photo = $this->file('photo');

                if (!$photo || !$photo->isValid()) {
                    return;
                }

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

                // Duplicate photo check
                $photoExists = Photo::where([
                    'user_id' => auth()->id(),
                    'datetime' => $dateTime
                ])->exists();

                if ($photoExists) {
                    $validator->errors()->add('photo', 'You have already uploaded this photo');
                    return;
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

                // Note: 0,0 coordinates are accepted. Future feature: manual coordinate assignment.
            }
        ];
    }
}
