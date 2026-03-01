<?php

namespace App\Http\Requests\Teams;

use Illuminate\Foundation\Http\FormRequest;

class CreateParticipantsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller
    }

    public function rules(): array
    {
        return [
            'count' => 'required_without:slots|integer|min:1|max:100',
            'slots' => 'required_without:count|array|min:1|max:100',
            'slots.*.display_name' => 'sometimes|string|max:100',
        ];
    }
}
