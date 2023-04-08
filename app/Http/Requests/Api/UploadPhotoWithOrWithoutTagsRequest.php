<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

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
}
