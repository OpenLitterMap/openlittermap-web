<?php

namespace App\Http\Requests\Api;

use App\Models\Photo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReplacePhotoTagsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $photo = Photo::find($this->input('photo_id'));

        if (! $photo) {
            return true;
        }

        // Ownership check only — no verification gate (allows re-tagging)
        return $photo->user_id === $this->user()->id;
    }

    public function rules(): array
    {
        return [
            'photo_id' => ['required', 'integer', Rule::exists('photos', 'id')->whereNull('deleted_at')],
            'tags' => 'required|array|min:1',

            // New CLO-based format
            'tags.*.category_litter_object_id' => 'sometimes|integer|exists:category_litter_object,id',
            'tags.*.litter_object_type_id' => 'nullable|integer|exists:litter_object_types,id',
            'tags.*.quantity' => 'sometimes|integer|min:1',
            'tags.*.picked_up' => 'nullable|boolean',
            'tags.*.materials' => 'sometimes|array',
            'tags.*.brands' => 'sometimes|array',
            'tags.*.custom_tags' => 'sometimes|array',

            // Legacy format fields (backward compat)
            'tags.*.category' => 'sometimes',
            'tags.*.object' => 'sometimes',
            'tags.*.brand_only' => 'sometimes',
            'tags.*.brand' => 'sometimes',
            'tags.*.material_only' => 'sometimes',
            'tags.*.material' => 'sometimes',
            'tags.*.custom' => 'sometimes',
            'tags.*.key' => 'sometimes',
        ];
    }
}
