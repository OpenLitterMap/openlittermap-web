<?php

namespace App\Http\Requests\Teams;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeamRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     * Ignoring the team we're updating,
     * since we don't want to test for uniqueness against it
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'min:3',
                'max:100',
                Rule::unique('teams')->ignore($this->route('team'))
            ],
            'identifier' => [
                'required',
                'min:3',
                'max:15',
                Rule::unique('teams')->ignore($this->route('team'))
            ],
        ];
    }
}
