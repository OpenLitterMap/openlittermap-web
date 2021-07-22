<?php


namespace App\Actions\Photos;


use App\Models\Photo;

class ClearTagsOfPhotoAction
{
    /**
     * Clear all tags on an image
     * Returns the total number of tags that were deleted, excluding brands
     */
    public function run(Photo $photo)
    {
        $totalDeletedTags = 0;

        foreach ($photo->categories() as $category) {
            if ($photo->$category) {
                if ($category !== 'brands') {
                    $totalDeletedTags += $photo->$category->total();
                }

                $photo->$category->delete();
            }
        }

        return $totalDeletedTags;
    }
}
