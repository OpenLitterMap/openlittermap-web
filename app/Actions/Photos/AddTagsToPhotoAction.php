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
     * @return int number of added tags
     */
    public function run(Photo $photo, array $tags): int
    {
        foreach ($tags as $tagId => $quantity) {
            $photo->tags()->attach($tagId, ['quantity' => $quantity]);
        }

        return array_sum($tags);
    }
}
