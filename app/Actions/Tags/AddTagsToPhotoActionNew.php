<?php

namespace App\Actions\Tags;

use App\Models\Category;

class AddTagsToPhotoActionNew
{
    public function run (Category $category, array $tags): void
    {
        \Log::info('todo');
    }
}
