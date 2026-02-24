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
        ];
    }
}
