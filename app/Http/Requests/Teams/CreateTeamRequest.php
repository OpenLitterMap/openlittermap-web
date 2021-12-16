<?php

namespace App\Http\Requests\Teams;

use Illuminate\Foundation\Http\FormRequest;

class CreateTeamRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name' => 'required|min:3|max:100|unique:teams',
            'identifier' => 'required|min:3|max:15|unique:teams',
            'team_type' => 'required'
        ];
    }
}
