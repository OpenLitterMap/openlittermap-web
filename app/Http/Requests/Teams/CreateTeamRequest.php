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
            $rules['county'] = 'required|string|max:100';
            $rules['academic_year'] = 'nullable|string|max:20';
            $rules['class_group'] = 'nullable|string|max:100';
            $rules['logo'] = 'nullable|image|max:2048';
            $rules['max_participants'] = 'nullable|integer|min:1|max:500';
            $rules['participant_sessions_enabled'] = 'nullable|boolean';
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('participant_sessions_enabled')) {
            $this->merge([
                'participant_sessions_enabled' => filter_var($this->participant_sessions_enabled, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }
    }

    protected function isSchoolTeam(): bool
    {
        return TeamType::where('id', $this->input('teamType'))
            ->where('team', 'school')
            ->exists();
    }
}
