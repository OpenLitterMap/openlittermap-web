<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GetImageForVerificationRequest extends FormRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'country_id' => 'nullable|exists:countries,id',
            'skip' => 'nullable|numeric'
        ];
    }
}
