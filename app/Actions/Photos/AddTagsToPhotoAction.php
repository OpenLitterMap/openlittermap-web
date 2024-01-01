<?php

namespace App\Actions\Photos;

use App\Models\Photo;

class AddTagsToPhotoAction
{
    /**
     * Adds tags to the photo.
     *
     * @param $tags
     * @return array number of added tags, total litter and brands
     */
    public function run (Photo $photo, $tags): array
    {
        $photo->refresh();

        $litter = 0;
        $brands = 0;

        foreach ($tags as $category => $items)
        {
            $this->createCategory($photo, $category);

            $photo->fresh()->$category->update($items);

            if ($category === 'brands') {
                $brands += array_sum($items);
            } else {
                $litter += array_sum($items);
            }
        }

        $all = $litter + $brands;

        return ['litter' => $litter, 'brands' => $brands, 'all' => $all];
    }

    /**
     * Creates new rows on respective category tables
     */
    protected function createCategory (Photo $photo, string $category): void
    {
        $createdCategory = $photo->$category()->create();

        $photo->update([
            $category . "_id" => $createdCategory->id
        ]);
    }
}
