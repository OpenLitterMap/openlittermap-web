<?php

namespace App\Actions\QuickTags;

use App\Models\Users\User;
use App\Models\Users\UserQuickTag;
use Illuminate\Support\Facades\DB;

class SyncQuickTagsAction
{
    /**
     * Bulk-replace all quick tags for a user.
     * Deletes existing rows and inserts new ones in a transaction.
     *
     * @param User $user
     * @param array $tags Validated array of tag presets
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function run(User $user, array $tags)
    {
        return DB::transaction(function () use ($user, $tags) {
            UserQuickTag::where('user_id', $user->id)->delete();

            $now = now();
            $rows = [];

            foreach ($tags as $index => $tag) {
                $rows[] = [
                    'user_id' => $user->id,
                    'clo_id' => $tag['clo_id'],
                    'type_id' => $tag['type_id'] ?? null,
                    'quantity' => $tag['quantity'] ?? 1,
                    'picked_up' => $tag['picked_up'] ?? null,
                    'materials' => json_encode($tag['materials'] ?? []),
                    'brands' => json_encode($tag['brands'] ?? []),
                    'sort_order' => $index,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (!empty($rows)) {
                UserQuickTag::insert($rows);
            }

            return $user->quickTags()->get();
        });
    }
}
