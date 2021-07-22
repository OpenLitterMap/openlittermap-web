<?php

namespace App\Actions\Photos;

use App\Models\Photo;

class AddTagsToPhotoAction
{
    /**
     * Execute the action and return a result.
     *
     * @param Photo $photo
     * @param array $tags
     * @return int
     */
    public function run(Photo $photo, array $tags): int
    {
        $litterTotal = 0;

        foreach ($tags as $category => $items) {
            $this->createCategory($photo, $category);

            $photo->fresh()->$category->update($items);

            $litterTotal += array_sum($items);
        }

        return $litterTotal;
    }

    /**
     * @param Photo $photo
     * @param $category
     */
    protected function createCategory(Photo $photo, $category): void
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
