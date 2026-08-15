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
            $tags = $this->repointRetiredObjects($tags);

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
     * Stale mobile presets still name the retired CLO. Remount onto the survivor
     * before the bulk replace so a 7-day-old catalog can sync. Retired with no
     * merge still 422s — and that refusal is ahead of the delete so existing
     * presets are left untouched.
     *
     * @param  array<int, array{clo_id: int}>  $tags
     * @return array<int, array{clo_id: int}>
     *
     * @throws ValidationException
     */
    private function repointRetiredObjects(array $tags): array
    {
        $cloIds = array_unique(array_map(static fn (array $tag): int => (int) $tag['clo_id'], $tags));

        if ($cloIds === []) {
            return $tags;
        }

        $clos = CategoryObject::query()
            ->whereIn('id', $cloIds)
            ->with('litterObject:id,key,retired_at,merged_into_id')
            ->get()
            ->keyBy('id');

        $unmapped = [];

        foreach ($tags as $i => $tag) {
            $clo = $clos->get((int) $tag['clo_id']);

            if ($clo === null || ! $clo->litterObject?->isRetired()) {
                continue;
            }

            $target = $clo->writeTarget();

            if ($target === null) {
                $unmapped[] = $clo->litterObject->key;

                continue;
            }

            $tags[$i]['clo_id'] = $target->id;
        }

        if ($unmapped !== []) {
            throw ValidationException::withMessages([
                'tags' => ['Retired and can no longer be saved as a quick tag: ' . implode(', ', array_unique($unmapped)) . '.'],
            ]);
        }

        return $tags;
    }
}
