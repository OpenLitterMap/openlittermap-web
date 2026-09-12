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
            'tags' => 'present|array',

            // - CLO ID and extra-tag fields.
            'tags.*.category_litter_object_id' => 'sometimes|integer|exists:category_litter_object,id',
            'tags.*.litter_object_type_id' => 'nullable|integer|exists:litter_object_types,id',
            'tags.*.quantity' => 'sometimes|integer|min:1',
            'tags.*.picked_up' => 'nullable|boolean',
            'tags.*.materials' => 'sometimes|array',
            'tags.*.brands' => 'sometimes|array',
            'tags.*.custom_tags' => 'sometimes|array',

            // - Object/category fallback and standalone extras; the shared action validates them.
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

    /**
     * - A missing CLO ID returns 422 and asks the user to refresh the tag list.
     * - Example: category_litter_object_id refers to a CLO row that no longer exists.
     * - Normal retirement keeps the old CLO row; the tag action follows its redirect.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tags.*.category_litter_object_id.exists' => 'This tag is no longer available — refresh your tag list.',
        ];
    }
}
