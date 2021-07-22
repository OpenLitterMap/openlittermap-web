<?php

namespace App\Actions\Photos;

use App\Models\Photo;

class AddTagsToPhotoAction
{
    /**
     * Adds tags to the photo.
     * Creates new rows on respective category tables
     *
     * @param Photo $photo
     * @param array $tags
     * @return int number of added tags, excluding brands
     */
    public function run(Photo $photo, array $tags): int
    {
        $litterTotal = 0;

        foreach ($tags as $category => $items) {
            $this->createCategory($photo, $category);

            $photo->fresh()->$category->update($items);

            if ($category !== 'brands') {
                $litterTotal += array_sum($items);
            }
        }

        return $litterTotal;
    }

    /**
     * @param Photo $photo
     * @param string $category
     */
    protected function createCategory(Photo $photo, string $category): void
    {
        if ($photo->$category) {
            return;
        }

        $createdCategory = $photo->$category()->create();

        $photo->update([
            $category . "_id" => $createdCategory->id
        ]);
    }
}
