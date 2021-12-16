<?php

namespace App\Http\Requests\Teams;

use Illuminate\Foundation\Http\FormRequest;

class JoinTeamRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'identifier' => 'required|min:3|max:15|exists:teams,identifier'
        ];
    }

    public function messages(): array
    {
        return [
            'exists' => 'Sorry, we could not find a team with this identifier.'
        ];
    }
}
