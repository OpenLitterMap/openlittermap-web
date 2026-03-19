<?php

namespace App\Http\Requests\Location;

use Illuminate\Validation\Rule;

class LeaderboardRequest extends BaseLocationRequest
{
    public function rules(): array
    {
        return [
            'period' => Rule::in(['today', 'this_week', 'this_month', 'this_year', 'all_time']),
        ];
    }

    public function getPeriod(): string
    {
        return $this->validated()['period'] ?? 'all_time';
    }
}
