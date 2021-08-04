<?php

namespace App\Actions\Photos;

use App\Models\Photo;

class AddTagsToPhotoAction
{
    /**
     * Adds tags to the photo.
     *
     * @param Photo $photo
     * @param array $tags
     *
     * @return array number of added tags, total litter and brands
     */
    public function run (Photo $photo, array $tags) :array
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

        return compact('litter', 'brands', 'all');
    }

    /**
     * Creates new rows on respective category tables
     *
     * @param Photo $photo
     * @param string $category
     */
    protected function createCategory (Photo $photo, string $category) :void
    {
        $createdCategory = $photo->$category()->create();

        $photo->update([
            $category . "_id" => $createdCategory->id
        ]);
    }
}
