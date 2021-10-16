<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContactRequest extends FormRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'subject' => 'required|max:255',
            'message' => 'required|max:5000',
            'name' => 'nullable|max:255',
            'email' => 'required|email',
            'g-recaptcha-response' => 'required|captcha'
        ];
    }
}
