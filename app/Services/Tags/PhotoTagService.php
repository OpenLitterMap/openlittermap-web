<?php

namespace App\Services\Tags;

use App\Models\Litter\Tags\PhotoTag;

/**
 * @deprecated
 */
class PhotoTagService
{
    public function createTag(array $data): PhotoTag
    {
        return PhotoTag::firstOrCreate([
            'photo_id'         => $data['photo_id'],
            'category_id'      => $data['category_id'] ?? null,
            'litter_object_id' => $data['litter_object_id'] ?? null,
            'material_id'      => $data['material_id'] ?? null,
            'brand_id'         => $data['brand_id'] ?? null,
        ], [
            'quantity'         => $data['quantity'] ?? 1,
        ]);
    }
}
