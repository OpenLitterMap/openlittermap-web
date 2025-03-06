<?php

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class PhotoTagsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'photo_id' => [
                'required',
                'integer',
                Rule::exists('photos', 'id')->where(function ($query) {
                    $query->where('user_id', $this->user()->id);
                }),
            ],
            'tags' => 'required|array',
        ];
    }
}
