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

        $litter = $this->deleteLitter($photo);
        $custom = $this->deleteCustomTags($photo);

        $all = $litter + $custom;

        return compact('litter', 'custom', 'all');
    }

    private function deleteLitter(Photo $photo): int
    {
        $categories = collect($photo->categories())
            ->filter(function ($category) use ($photo) {
                return $category !== 'brands' && !!$photo->$category;
            });

        $total = $categories->sum(function ($category) use ($photo) {
            return $photo->$category->total();
        });

        $categories->each(function ($category) use ($photo) {
            $photo->$category->delete();
        });

        return $total;
    }

    private function deleteCustomTags(Photo $photo): int
    {
        $total = $photo->customTags->count();

        $photo->customTags()->delete();

        return $total;
    }
}
