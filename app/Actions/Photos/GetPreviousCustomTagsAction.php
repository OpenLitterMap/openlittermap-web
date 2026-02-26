<?php

namespace App\Actions\Photos;

use App\Models\Users\User;
use Illuminate\Support\Collection;

/**
 * @deprecated
 */
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
