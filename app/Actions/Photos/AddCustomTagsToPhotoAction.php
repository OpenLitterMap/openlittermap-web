<?php

namespace App\Actions\Photos;

use App\Models\Photo;

class AddCustomTagsToPhotoAction
{
    /**
     * Adds custom tags to the photo.
     */
    public function run(Photo $photo, array $tags): int
    {
        if (empty($tags)) {
            return 0;
        }

        $photo->customTags()->createMany(
            collect($tags)->map(function ($tag) {
                return ['tag' => $tag];
            })
        );

        return count($tags);
    }
}
