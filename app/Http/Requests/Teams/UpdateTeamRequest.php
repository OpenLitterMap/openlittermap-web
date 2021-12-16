<?php

namespace App\Http\Requests\Teams;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeamRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'min:3',
                'max:100',
                Rule::unique('teams')->ignore($this->get('id'))
            ],
            'identifier' => [
                'required',
                'min:3',
                'max:15',
                Rule::unique('teams')->ignore($this->get('id'))
            ],
        ];
    }
}
