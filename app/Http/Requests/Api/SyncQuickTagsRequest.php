<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class SyncQuickTagsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'tags' => 'present|array|max:30',
            'tags.*.clo_id' => 'required|integer|exists:category_litter_object,id',
            'tags.*.type_id' => 'nullable|integer|exists:litter_object_types,id',
            'tags.*.quantity' => 'required|integer|min:1|max:10',
            'tags.*.picked_up' => 'nullable|boolean',
            'tags.*.materials' => 'present|array',
            'tags.*.materials.*' => 'integer|exists:materials,id',
            'tags.*.brands' => 'present|array',
            'tags.*.brands.*.id' => 'required|integer|exists:brandslist,id',
            'tags.*.brands.*.quantity' => 'required|integer|min:1|max:10',
        ];
    }

    public function messages(): array
    {
        return [
            'tags.max' => 'Maximum 30 quick tags allowed.',
            'tags.*.clo_id.exists' => 'One or more tag IDs are no longer valid. Please remove stale tags and try again.',
        ];
    }
}
