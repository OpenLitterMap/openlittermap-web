<?php

namespace App\Actions\Photos;

use App\Models\User\User;
use Illuminate\Support\Collection;

class GetPreviousCustomTagsAction
{
    public function run(User $user): Collection
    {
        return $user->customTags()
            ->distinct('tag')
            ->get(['tag'])
            ->pluck('tag');
    }
}
