<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
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
            'social_twitter' => 'nullable|url',
            'social_facebook' => 'nullable|url',
            'social_instagram' => 'nullable|url',
            'social_linkedin' => 'nullable|url',
            'social_reddit' => 'nullable|url',
            'social_personal' => 'nullable|url',
        ];
    }

    public function messages()
    {
        return [
            'social_twitter.url' => 'The Twitter URL is invalid.',
            'social_facebook.url' => 'The Facebook URL is invalid.',
            'social_instagram.url' => 'The Instagram URL is invalid.',
            'social_linkedin.url' => 'The LinkedIn URL is invalid.',
            'social_reddit.url' => 'The Reddit URL is invalid.',
            'social_personal.url' => 'The personal website\'s URL is invalid.',
        ];
    }
}
