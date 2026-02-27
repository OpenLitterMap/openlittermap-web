<?php

namespace App\Actions\Tags;

use App\Enums\CategoryKey;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CategoryObject;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Photo;
use Illuminate\Support\Facades\Log;

/**
 * Converts v4 mobile tag payloads directly to v5 PhotoTags.
 *
 * Maps v4 format {categoryKey: {objectKey: quantity}} directly to CLO ids
 * and delegates to the v5 AddTagsToPhotoAction (summary, XP, verification).
 */
class ConvertV4TagsAction
{
    public function __construct(
        private AddTagsToPhotoAction $addTagsAction,
    ) {}

    /**
     * @param int   $userId
     * @param int   $photoId
     * @param array $v4Tags     v4 format {categoryKey: {objectKey: quantity}}
     * @param bool  $pickedUp
     * @param array $customTags Optional custom tag strings
     */
    public function run(int $userId, int $photoId, array $v4Tags, bool $pickedUp, array $customTags = []): void
    {
        $photo = Photo::find($photoId);

        if (! $photo) {
            Log::warning('ConvertV4TagsAction: photo not found', ['photo_id' => $photoId]);

            return;
        }

        // Idempotency: skip if already has v5 tags
        if ($photo->photoTags()->exists()) {
            return;
        }

        // Set remaining before summary generation (affects XP picked_up bonus)
        $photo->remaining = ! $pickedUp;
        $photo->save();

        // Convert v4 → v5 format
        $v5Tags = $this->convertToV5Format($v4Tags, $customTags, $pickedUp);

        if (empty($v5Tags)) {
            Log::warning('ConvertV4TagsAction: no valid tags after conversion', [
                'photo_id' => $photoId,
                'v4_tags' => $v4Tags,
            ]);

            return;
        }

        // AddTagsToPhotoAction handles: PhotoTag creation, summary, XP, verification
        $this->addTagsAction->run($userId, $photoId, $v5Tags);
    }

    /**
     * Convert v4 tag format to v5 tag array suitable for AddTagsToPhotoAction.
     */
    private function convertToV5Format(array $v4Tags, array $customTags, bool $pickedUp): array
    {
        $tags = [];

        foreach ($v4Tags as $categoryKey => $items) {
            if (! is_array($items)) {
                continue;
            }

            $category = Category::where('key', $categoryKey)->first();
            if (! $category) {
                Log::warning("ConvertV4TagsAction: unknown category '{$categoryKey}', skipping");

                continue;
            }

            foreach ($items as $objectKey => $quantity) {
                $qty = (int) $quantity;
                if ($qty <= 0) {
                    continue;
                }

                $object = LitterObject::where('key', $objectKey)->first();
                if (! $object) {
                    Log::warning("ConvertV4TagsAction: unknown object '{$objectKey}' in '{$categoryKey}', skipping");

                    continue;
                }

                $clo = CategoryObject::where('category_id', $category->id)
                    ->where('litter_object_id', $object->id)
                    ->first();

                if (! $clo) {
                    Log::warning("ConvertV4TagsAction: no CLO for {$categoryKey}.{$objectKey}, skipping");

                    continue;
                }

                $tags[] = [
                    'category_litter_object_id' => $clo->id,
                    'quantity' => $qty,
                    'picked_up' => $pickedUp ?: null,
                ];
            }
        }

        // Custom tags — each becomes a separate tag on unclassified.other
        if (! empty($customTags)) {
            $unclassifiedClo = CategoryObject::query()
                ->whereHas('category', fn ($q) => $q->where('key', CategoryKey::Unclassified->value))
                ->whereHas('litterObject', fn ($q) => $q->where('key', 'other'))
                ->first();

            if ($unclassifiedClo) {
                foreach ($customTags as $customTag) {
                    $tags[] = [
                        'category_litter_object_id' => $unclassifiedClo->id,
                        'quantity' => 1,
                        'picked_up' => $pickedUp ?: null,
                        'custom_tags' => [$customTag],
                    ];
                }
            }
        }

        return $tags;
    }
}
