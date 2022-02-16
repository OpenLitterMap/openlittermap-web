<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UploadPhotoWithTagsRequest extends FormRequest
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
            'tags' => 'required_without:custom_tags|array',
            'picked_up' => 'nullable|boolean',
            'custom_tags' => 'required_without:tags|array|max:3',
            'custom_tags.*' => 'distinct:ignore_case|min:3|max:100'
        ];
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'tags' => json_decode($this->tags, true) ?? [],
            'custom_tags' => json_decode($this->custom_tags, true) ?? []
        ]);
    }
}
