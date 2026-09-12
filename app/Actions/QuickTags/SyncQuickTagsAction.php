<?php

namespace App\Actions\QuickTags;

use App\Models\Litter\Tags\CategoryObject;
use App\Models\Users\User;
use App\Models\Users\UserQuickTag;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
            $tags = $this->repointToActiveClos($tags);

            UserQuickTag::where('user_id', $user->id)->delete();

            $now = now();
            $rows = [];

            foreach ($tags as $index => $tag) {
                $rows[] = [
                    'user_id' => $user->id,
                    'clo_id' => $tag['clo_id'],
                    'type_id' => $tag['type_id'] ?? null,
                    'custom_name' => $tag['custom_name'] ?? null,
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

    /**
     * Replace retired clo_id with an existing approved active CLO. Normal API writes never
     * create category/object relationships; a missing target is refused before the bulk delete.
     *
     * @param  array<int, array{clo_id: int}>  $tags
     * @return array<int, array{clo_id: int}>
     *
     * @throws ValidationException
     */
    private function repointToActiveClos(array $tags): array
    {
        foreach ($tags as $i => $tag) {
            $clo = CategoryObject::find($tag['clo_id']);
            if ($clo === null) {
                throw ValidationException::withMessages([
                    'tags' => ['This tag is no longer available — refresh your tag list.'],
                ]);
            }
            $mapping = $clo->resolveForWrite($tag['type_id'] ?? null);
            $tags[$i]['clo_id'] = $mapping['clo']->id;
            $tags[$i]['type_id'] = $mapping['type_id'];
        }

        return $tags;
    }
}
