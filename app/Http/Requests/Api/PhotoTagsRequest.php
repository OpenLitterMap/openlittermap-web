<?php

namespace App\Http\Requests\Api;

use App\Enums\VerificationStatus;
use App\Models\Photo;
use Illuminate\Foundation\Http\FormRequest;

class PhotoTagsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $photo = Photo::find($this->input('photo_id'));

        // If photo doesn't exist, let validation handle it (422)
        if (! $photo) {
            return true;
        }

        // Ownership check (403)
        if ($photo->user_id !== $this->user()->id) {
            return false;
        }

        // Already verified check (403)
        if ($photo->verified->value >= VerificationStatus::VERIFIED->value) {
            return false;
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'photo_id' => 'required|integer|exists:photos,id',
            'tags' => 'required|array|min:1',

            // New CLO-based format
            'tags.*.category_litter_object_id' => 'sometimes|integer|exists:category_litter_object,id',
            'tags.*.litter_object_type_id' => 'nullable|integer|exists:litter_object_types,id',
            'tags.*.quantity' => 'sometimes|integer|min:1',
            'tags.*.picked_up' => 'nullable|boolean',
            'tags.*.materials' => 'sometimes|array',
            'tags.*.brands' => 'sometimes|array',
            'tags.*.custom_tags' => 'sometimes|array',

            // Legacy format fields (backward compat — action handles validation)
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
