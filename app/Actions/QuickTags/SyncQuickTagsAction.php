<?php

namespace App\Actions\QuickTags;

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
            $this->rejectRetiredObjects($tags);

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
     * A retired object is being drained by a retirement run. `clo_id` validation only proves the
     * pivot exists — the pivot outlives the picker — so the check runs here, inside the same
     * transaction as the bulk replace and ahead of the delete, and a refusal rolls the whole
     * sync back rather than leaving the user with no presets at all.
     *
     * @param array<int, array{clo_id: int}> $tags
     *
     * @throws ValidationException
     */
    private function rejectRetiredObjects(array $tags): void
    {
        $cloIds = array_unique(array_map(static fn (array $tag): int => (int) $tag['clo_id'], $tags));

        if (empty($cloIds)) {
            return;
        }

        $retired = DB::table('category_litter_object')
            ->join('litter_objects', 'litter_objects.id', '=', 'category_litter_object.litter_object_id')
            ->whereIn('category_litter_object.id', $cloIds)
            ->whereNotNull('litter_objects.retired_at')
            ->pluck('litter_objects.key')
            ->all();

        if (empty($retired)) {
            return;
        }

        throw ValidationException::withMessages([
            'tags' => ['Retired and can no longer be saved as a quick tag: ' . implode(', ', $retired) . '.'],
        ]);
    }
}
