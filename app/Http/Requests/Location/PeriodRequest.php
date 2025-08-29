<?php

namespace App\Http\Requests\Location;

use Illuminate\Validation\Rule;

class PeriodRequest extends BaseLocationRequest
{
    public function rules(): array
    {
        return [
            'period' => Rule::in(['daily', 'weekly', 'monthly']),
        ];
    }

    public function getPeriod(): string
    {
        return $this->validated()['period'] ?? 'daily';
    }
}
