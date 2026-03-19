<?php

namespace App\Http\Requests\Points;

use Illuminate\Foundation\Http\FormRequest;

class PointsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'zoom' => 'required|integer|min:15|max:20',
            'bbox.left' => 'required|numeric|between:-180,180',
            'bbox.bottom' => 'required|numeric|between:-90,90',
            'bbox.right' => 'required|numeric|between:-180,180',
            'bbox.top' => 'required|numeric|between:-90,90',
            'categories' => 'array',
            'categories.*' => 'string|distinct|exists:categories,key',
            'litter_objects' => 'array',
            'litter_objects.*' => 'string|distinct|exists:litter_objects,key',
            'materials' => 'array',
            'materials.*' => 'string|distinct|exists:materials,key',
            'brands' => 'array',
            'brands.*' => 'string|distinct|exists:brandslist,key',
            'custom_tags' => 'array',
            'custom_tags.*' => 'string|distinct|exists:custom_tags_new,key',
            'per_page' => 'integer|min:1|max:500',
            'page' => 'integer|min:1',
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
            'username' => 'string',
            'year' => 'nullable|integer|min:2017|max:' . date('Y')
        ];
    }

    /**
     * Get validation rules for stats requests (excludes pagination)
     * This method is kept for backward compatibility but the PointsStatsRequest
     * class should be used instead.
     */
    public function statsRules(): array
    {
        $rules = $this->rules();

        // Remove pagination-specific rules
        unset($rules['per_page'], $rules['page']);

        return $rules;
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $this->validateBbox($validator);
        });
    }

    /**
     * Validate bbox parameters
     */
    protected function validateBbox($validator): void
    {
        if ($validator->errors()->any()) {
            return; // Skip bbox validation if basic validation failed
        }

        $bbox = $this->input('bbox');

        if (!$bbox || !is_array($bbox)) {
            return;
        }

        // Validate bbox ordering
        if (
            !isset($bbox['left'], $bbox['right'], $bbox['bottom'], $bbox['top']) ||
            $bbox['left'] >= $bbox['right'] ||
            $bbox['bottom'] >= $bbox['top']
        ) {
            $validator->errors()->add(
                'bbox',
                'Invalid bounding box: left must be < right and bottom must be < top'
            );
            return;
        }

        // Validate bbox size based on zoom level
        $zoom = $this->input('zoom');
        if (!$zoom) {
            return; // Zoom validation will catch this
        }

        $width = $bbox['right'] - $bbox['left'];
        $height = $bbox['top'] - $bbox['bottom'];
        $area = $width * $height;

        $maxAreas = [
            15 => 100,   // 10° x 10°
            16 => 25,    // 5° x 5°
            17 => 10,    // ~3° x 3°
            18 => 4,     // 2° x 2°
            19 => 1,     // 1° x 1°
            20 => 0.25   // 0.5° x 0.5°
        ];

        if (isset($maxAreas[$zoom]) && $area > $maxAreas[$zoom]) {
            $validator->errors()->add(
                'bbox',
                "Bounding box too large for zoom level {$zoom}"
            );
        }
    }

    /**
     * Get custom error messages
     */
    public function messages(): array
    {
        return [
            'zoom.required' => 'Zoom level is required',
            'zoom.integer' => 'Zoom level must be an integer',
            'zoom.min' => 'Zoom level must be at least 15',
            'zoom.max' => 'Zoom level must be at most 20',

            'bbox.left.required' => 'Bounding box left coordinate is required',
            'bbox.right.required' => 'Bounding box right coordinate is required',
            'bbox.bottom.required' => 'Bounding box bottom coordinate is required',
            'bbox.top.required' => 'Bounding box top coordinate is required',

            'bbox.*.numeric' => 'Bounding box coordinates must be numeric',
            'bbox.left.between' => 'Left coordinate must be between -180 and 180',
            'bbox.right.between' => 'Right coordinate must be between -180 and 180',
            'bbox.bottom.between' => 'Bottom coordinate must be between -90 and 90',
            'bbox.top.between' => 'Top coordinate must be between -90 and 90',

            'categories.array' => 'Categories must be an array',
            'categories.*.exists' => 'One or more selected categories do not exist',
            'categories.*.distinct' => 'Categories must be unique',

            'litter_objects.array' => 'Litter objects must be an array',
            'litter_objects.*.exists' => 'One or more selected litter objects do not exist',
            'litter_objects.*.distinct' => 'Litter objects must be unique',

            'materials.array' => 'Materials must be an array',
            'materials.*.exists' => 'One or more selected materials do not exist',
            'materials.*.distinct' => 'Materials must be unique',

            'brands.array' => 'Brands must be an array',
            'brands.*.exists' => 'One or more selected brands do not exist',
            'brands.*.distinct' => 'Brands must be unique',

            'custom_tags.array' => 'Custom tags must be an array',
            'custom_tags.*.exists' => 'One or more selected custom tags do not exist',
            'custom_tags.*.distinct' => 'Custom tags must be unique',

            'per_page.integer' => 'Items per page must be an integer',
            'per_page.min' => 'Items per page must be at least 1',
            'per_page.max' => 'Items per page must be at most 500',

            'page.integer' => 'Page number must be an integer',
            'page.min' => 'Page number must be at least 1',

            'from.date_format' => 'From date must be in Y-m-d format',
            'to.date_format' => 'To date must be in Y-m-d format',
            'to.after_or_equal' => 'To date must be after or equal to from date',

            'year.integer' => 'Year must be an integer',
            'year.min' => 'Year must be 2017 or later',
            'year.max' => 'Year cannot be in the future',
        ];
    }

    /**
     * Get validated data excluding pagination parameters (for stats)
     */
    public function getStatsData(): array
    {
        $validated = $this->validated();

        // Remove pagination parameters
        unset($validated['per_page'], $validated['page']);

        return $validated;
    }
}
