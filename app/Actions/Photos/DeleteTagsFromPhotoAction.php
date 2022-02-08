<?php

namespace App\Actions\Photos;

use App\Models\Photo;
use Illuminate\Support\Collection;

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
    public function run(Photo $photo): array
    {
        $photo->refresh();

        $categories = collect($photo->categories())
            ->filter(function ($category) use ($photo) {
                return !!$photo->$category;
            });

        $litter = $this->deleteLitter($photo, $categories);
        $brands = $this->deleteBrands($photo);
        $custom = $this->deleteCustomTags($photo);

        $all = $litter + $brands + $custom;

        return compact('litter', 'brands', 'custom', 'all');
    }

    private function deleteLitter(Photo $photo, Collection $categories): int
    {
        $total = $categories
            ->filter(function ($category) {
                return $category !== 'brands';
            })
            ->sum(function ($category) use ($photo) {
                return $photo->$category->total();
            });

        $categories->each(function ($category) use ($photo) {
            $photo->$category->delete();
        });

        return $total;
    }

    private function deleteBrands(Photo $photo): int
    {
        $total = $photo->brands->total();

        $photo->brands->delete();

        return $total;
    }

    private function deleteCustomTags(Photo $photo): int
    {
        $total = $photo->customTags->count();

        $photo->customTags()->delete();

        return $total;
    }
}
