<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

class UploadPhotoWithOrWithoutTagsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'photo' => 'required|mimes:jpg,png,jpeg,heif,heic',
            'lat' => 'required|numeric',
            'lon' => 'required|numeric',
            'date' => 'required',
            'picked_up' => 'nullable|boolean',

            // Note: on API upload, custom tags are encoded as strings
//            'custom_tags' => 'sometimes|array|max:3',
//            'custom_tags.*' => 'sometimes|distinct:ignore_case|min:3|max:100'
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        $file = $this->file('photo');

        $photoInfo = [];
        if ($file instanceof \Illuminate\Http\UploadedFile) {
            $photoInfo = [
                'photo_original_name' => $file->getClientOriginalName(),
                'photo_mime' => $file->getMimeType(),
                'photo_extension' => $file->getClientOriginalExtension(),
                'photo_size' => $file->getSize(),
                'photo_error' => $file->getError(),
            ];
        }

        Log::warning('Mobile upload v2: validation failed', array_merge([
            'user_id' => auth()->id(),
            'errors' => $validator->errors()->toArray(),
            'has_photo' => $this->hasFile('photo'),
            'content_type' => $this->header('Content-Type'),
        ], $photoInfo));

        parent::failedValidation($validator);
    }
}
