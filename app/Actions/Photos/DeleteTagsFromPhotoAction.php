<?php

namespace App\Actions\Photos;

use App\Events\TagsDeletedByAdmin;
use App\Models\Photo;

class DeleteTagsFromPhotoAction
{
    /**
     * Clear all tags on an image
     *
     * Returns the total number of tags that were deleted, separated from brands
     *
     * @param Photo $photo
     *
     * @return array
     */
    public function run (Photo $photo) :array
    {
        $photo->refresh();

        $litter = 0;
        $brands = 0;
        $deletedLitterTags = [];
        $deletedBrandsTags = [];

        foreach ($photo->categories() as $category)
        {
            if ($photo->$category)
            {
                $categoryTotal = $photo->$category->total();

                if ($category === 'brands')
                {
                    $brands += $categoryTotal;

                    foreach (Photo::getBrands() as $brand) {
                        $deletedBrandsTags[$brand] = $photo->brands->$brand;
                    }
                }
                else
                {
                    $litter += $categoryTotal;

                    $deletedLitterTags[$category] = $categoryTotal;
                }

                $photo->$category->delete();
            }
        }

        $all = $litter + $brands;

        // Todo add a test for this
        event(new TagsDeletedByAdmin(
            $photo,
            $all,
            $litter,
            $brands,
            $deletedLitterTags,
            $deletedBrandsTags
        ));

        return compact('litter', 'brands', 'all');
    }
}
