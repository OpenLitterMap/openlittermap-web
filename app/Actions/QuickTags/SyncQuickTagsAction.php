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
        $cloIds = array_unique(array_map(static fn (array $tag): int => (int) $tag['clo_id'], $tags));

        if ($cloIds === []) {
            return $tags;
        }

        $clos = CategoryObject::withRetirementState($cloIds);

        $unmapped = [];

        foreach ($tags as $i => $tag) {
            $clo = $clos->get((int) $tag['clo_id']);

            // A pairing can be retired while its object stays live (a pure category move), so
            // the pivot's own retirement is checked, not only the object's.
            if ($clo === null || (! $clo->isRetired() && ! $clo->litterObject?->isRetired())) {
                continue;
            }

            $mapping = $clo->resolveActiveMapping();

            if ($mapping === null) {
                $unmapped[] = $clo->litterObject->key;

                continue;
            }

            $tags[$i]['clo_id'] = $mapping['clo']->id;

            // A v4 composite key split into object + type: a preset saved before the split has no
            // type of its own, so it takes the approved subtype recorded on the chain.
            if (($tag['type_id'] ?? null) === null && $mapping['type_id'] !== null) {
                $tags[$i]['type_id'] = $mapping['type_id'];
            }

            // A type the survivor pairing does not approve would make the preset unusable: every
            // tag submitted from it is refused. Drop it, as the photo-tag remount does.
            $typeId = $tags[$i]['type_id'] ?? null;

            if ($typeId !== null && ! DB::table('category_object_types')
                ->where('category_litter_object_id', $mapping['clo']->id)
                ->where('litter_object_type_id', $typeId)
                ->exists()) {
                $tags[$i]['type_id'] = null;
            }
        }

        if ($unmapped !== []) {
            throw ValidationException::withMessages([
                'tags' => ['Retired and can no longer be saved as a quick tag: ' . implode(', ', array_unique($unmapped)) . '.'],
            ]);
        }

        return $tags;
    }
}
