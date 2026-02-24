<?php

namespace App\Http\Requests\Teams;

use App\Models\Teams\TeamType;
use Illuminate\Foundation\Http\FormRequest;

class CreateTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->isSchoolTeam()) {
            return $this->user()->hasRole('school_manager');
        }

        return true;
    }

    public function rules(): array
    {
        $rules = [
            'name' => 'required|min:3|max:100|unique:teams',
            'identifier' => 'required|min:3|max:100|unique:teams',
            'teamType' => 'required|exists:team_types,id',
        ];

        if ($this->isSchoolTeam()) {
            $rules['contact_email'] = 'required|email|max:255';
            $rules['school_roll_number'] = 'nullable|string|max:50';
            $rules['county'] = 'nullable|string|max:100';
            $rules['academic_year'] = 'nullable|string|max:20';
            $rules['class_group'] = 'nullable|string|max:100';
        }

        return $rules;
    }

    protected function isSchoolTeam(): bool
    {
        return TeamType::where('id', $this->input('teamType'))
            ->where('team', 'school')
            ->exists();
    }
}
