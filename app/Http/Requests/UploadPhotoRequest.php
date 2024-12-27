<?php

namespace App\Http\Requests;

use App\Models\Photo;
use Illuminate\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UploadPhotoRequest extends FormRequest
{
    public $stopOnFirstFailure = true;

    protected string $gpsError1 = "Sorry, no GPS on this one.";

    protected string $gpsError2 = "Error: Your Images have GeoTags, but they have values of zero.
        You may have lost the geotags when transferring images across devices
        or you might need to enable another setting to make them available.";

    public function rules(): array
    {
        return [
            'photo' => [
                'required',
                'mimes:jpg,png,jpeg,heif,heic',
                'dimensions:min_width=1,min_height=1',
                'max:20480'
            ]
        ];
    }

    public function failedValidation(Validator|\Illuminate\Contracts\Validation\Validator $validator)
    {
        throw new \Illuminate\Validation\ValidationException($validator, response()->json([
            'error' => $validator->errors()->first('photo')
        ], 422));
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $photo = $this->photo;

                \Log::info($photo);

                $exif = exif_read_data($photo->getRealPath());

                $dateTime = getDateTimeForPhoto($exif);

                $photoExists = Photo::where([
                    'user_id' => auth()->id(),
                    'datetime' => $dateTime
                ])->exists();

                if ($photoExists) {
                    $validator->errors()->add('photo', 'You have already uploaded this photo');
                }

                if (!array_key_exists("GPSLatitudeRef", $exif)) {
                    $validator->errors()->add('photo', $this->gpsError1);
                }

                // Check if the EXIF has GPS data
                // todo - translate the error
                if (!array_key_exists("GPSLatitudeRef", $exif))
                {
                    $validator->errors()->add('photo', $this->gpsError1);
                }

                if ($exif["GPSLatitude"][0] === "0/0" && $exif["GPSLongitude"][0] === "0/0")
                {
                    $validator->errors()->add('photo', $this->gpsError2);
                }

                $coordinates = getCoordinatesFromPhoto($exif);

                $lat = $coordinates[0];
                $lon = $coordinates[1];

                if (($lat === 0 && $lon === 0) || ($lat === '0' && $lon === '0'))
                {
                    $validator->errors()->add('photo', $this->gpsError2);
                }
            }
        ];
    }

}
