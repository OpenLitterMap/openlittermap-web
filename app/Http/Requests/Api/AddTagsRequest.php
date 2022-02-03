<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class AddTagsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'photo_id' => 'required|exists:photos,id',
            'litter' => 'required_without:tags|array',
            'tags' => 'required_without:litter|array',
            'picked_up' => 'nullable|boolean',
            'custom_tags' => 'nullable|array|max:3',
            'custom_tags.*' => 'distinct:ignore_case|min:3|max:100'
        ];
    }
}
