<?php

namespace App\Actions\Photos;

use App\Models\Photo;
use App\Models\PhotoTag;

class DeleteTagsFromPhotoAction
{
    /**
     * Clear all tags on an image
     * Returns the total number of tags that were deleted
     *
     * @param Photo $photo
     * @return array
     */
    public function run(Photo $photo): array
    {
        $tags = $this->deleteTags($photo);
        $customTags = $photo->customTags()->delete();

        $all = $tags + $customTags;

        return compact('tags', 'customTags', 'all');
    }

    private function deleteTags(Photo $photo): int
    {
        $total = PhotoTag::query()->where('photo_id', $photo->id)->sum('quantity');

        $photo->tags()->detach();

        return $total;
    }
}
