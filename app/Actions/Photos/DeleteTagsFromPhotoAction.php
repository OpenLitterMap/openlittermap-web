<?php


namespace App\Actions\Photos;


use App\Models\Photo;

class DeleteTagsFromPhotoAction
{
    /**
     * Clear all tags on an image
     * Returns the total number of tags that were deleted, separated from brands
     */
    public function run(Photo $photo): array
    {
        $photo->refresh();

        $litter = 0;
        $brands = 0;

        foreach ($photo->categories() as $category) {
            if ($photo->$category) {
                if ($category === 'brands') {
                    $brands += $photo->$category->total();
                } else {
                    $litter += $photo->$category->total();
                }

                $photo->$category->delete();
            }
        }

        $all = $litter + $brands;

        return compact('litter', 'brands', 'all');
    }
}
