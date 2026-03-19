<?php

namespace App\Http\Requests\Points;

class PointsStatsRequest extends PointsRequest
{
    /**
     * Get the validation rules that apply to the request.
     * Excludes pagination rules since stats don't need pagination.
     */
    public function rules(): array
    {
        $rules = parent::rules();

        // Remove pagination-specific rules
        unset($rules['per_page'], $rules['page']);

        return $rules;
    }
}
