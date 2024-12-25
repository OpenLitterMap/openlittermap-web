<?php

namespace App\Http\Requests;

use App\Models\Photo;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UploadPhotoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
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

                $exif = exif_read_data($photo->getRealPath());

                $dateTime = getDateTimeForPhoto($exif);

                $photoExists = Photo::where([
                    'user_id' => auth()->id(),
                    'datetime' => $dateTime
                ])->exists();

                if ($photoExists) {
                    $validator->errors()->add('photo', 'You have already uploaded this photo');
                }
            }
        ];
    }

}
