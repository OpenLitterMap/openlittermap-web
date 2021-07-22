<?php


namespace App\Actions\Photos;


use App\Models\Photo;

class ClearTagsOfPhotoAction
{
    /**
     * Clear all tags on an image
     * Returns the total number of tags that were deleted
     */
    public function run(Photo $photo)
    {
        $totalDeletedTags = 0;

        foreach ($photo->categories() as $category) {
            if ($photo->$category) {
                $totalDeletedTags += $photo->$category->total();
                $photo->$category->delete();
            }
        }

        return $totalDeletedTags;
    }
}
