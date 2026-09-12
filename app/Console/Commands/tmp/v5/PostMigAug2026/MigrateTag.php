<?php

declare(strict_types=1);

namespace App\Console\Commands\tmp\v5\PostMigAug2026;

use App\Enums\XpScore;
use App\Models\Litter\Tags\CategoryObject;
use App\Models\Photo;
use App\Services\Metrics\MetricsService;
use App\Services\Tags\GeneratePhotoSummaryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Applies one approved tag mapping. The object is retired into a replacement when the mapping
 * changes it; a mapping that only moves the pairing to another category leaves the object in use.
 */
class MigrateTag extends Command
{
    protected $signature = 'olm:migrate-tag
        {retired : litter object key to retire}
        {desired : replacement litter object key}
        {--type= : litter object type key to set on the migrated rows (approved type splits only)}
        {--category= : target category key when the approved mapping moves the pairing}
        {--allow-xp-change : permit an approved mapping whose XP differs (re-scores every tag)}
        {--apply : execute the migration (dry-run by default)}';

    protected $description = 'Retire one litter object and move its data to another.';

    /** Set on the migrated rows when the approved mapping is a type split. */
    private ?int $typeId = null;

    private ?string $typeKey = null;

    /** Set when the approved mapping moves the pairing to another category. */
    private ?int $targetCategoryId = null;

    private ?string $targetCategoryKey = null;

    public function handle(GeneratePhotoSummaryService $summaryService, MetricsService $metricsService): int
    {
        $retiredKey = (string) $this->argument('retired');
        $desiredKey = (string) $this->argument('desired');

        if (!$this->resolveType() || !$this->resolveTargetCategory()) {
            return self::FAILURE;
        }

        $retired = DB::table('litter_objects')
            ->where('key', $retiredKey)
            ->select('id', 'retired_at', 'merged_into_id')
            ->first();
        $desired = DB::table('litter_objects')
            ->where('key', $desiredKey)
            ->select('id', 'retired_at')
            ->first();

        if ($retired === null) {
            $this->error("Unknown litter object: {$retiredKey}");

            return self::FAILURE;
        }

        if ($desired === null) {
            $this->error("Unknown litter object: {$desiredKey}");

            return self::FAILURE;
        }

        if ($this->alreadyApplied($retired, $desired)) {
            $this->info("Already applied: {$retiredKey} is retired into {$desiredKey} and nothing remains on it.");

            return self::SUCCESS;
        }

        if (!$this->mappingIsValid($retiredKey, $retired, $desiredKey, $desired)) {
            return self::FAILURE;
        }

        $entry = [
            'retired_key' => $retiredKey,
            'retired_id' => (int) $retired->id,
            'desired_key' => $desiredKey,
            'desired_id' => (int) $desired->id,
        ];
        $change = $this->measure((int) $retired->id);
        $this->report($entry, $change, !$this->option('apply'));

        if (!$this->option('apply')) {
            // A dry run rehearses every apply-time check, so a manifest rehearsal proves something.
            try {
                $this->planRetirement((int) $retired->id, (int) $desired->id);
            } catch (\RuntimeException $e) {
                $this->error('Would fail: ' . $e->getMessage());

                return self::FAILURE;
            }

            return self::SUCCESS;
        }

        if (!$this->redisIsReachable()) {
            return self::FAILURE;
        }

        return $this->apply($entry, $change['rows'], $summaryService, $metricsService)
            ? self::SUCCESS
            : self::FAILURE;
    }

    /**
     * A manifest replay must be safe. A mapping that finished — object retired into this
     * survivor, every source pairing retired, no rows or presets left — has nothing to do,
     * even when the survivor has since retired into something else.
     */
    private function alreadyApplied(object $retired, object $desired): bool
    {
        if ($retired->retired_at === null
            || (int) $retired->id === (int) $desired->id
            || (int) $retired->merged_into_id !== (int) $desired->id) {
            return false;
        }

        $rowsLeft = DB::table('photo_tags')->where('litter_object_id', $retired->id)->exists();
        $pivotsLeft = DB::table('category_litter_object')
            ->where('litter_object_id', $retired->id)
            ->whereNull('merged_into_clo_id')
            ->exists();
        $quickTagsLeft = DB::table('user_quick_tags as uqt')
            ->join('category_litter_object as clo', 'clo.id', '=', 'uqt.clo_id')
            ->where('clo.litter_object_id', $retired->id)
            ->exists();

        if ($rowsLeft || $pivotsLeft || $quickTagsLeft) {
            return false;
        }

        // "Applied" means applied as requested: a replay that names a different category or type
        // is a conflicting retry, which the immutability check must refuse, not a no-op.
        $disagreeing = DB::table('category_litter_object as source')
            ->join('category_litter_object as target', 'target.id', '=', 'source.merged_into_clo_id')
            ->where('source.litter_object_id', $retired->id)
            ->where(function ($q) use ($desired) {
                $q->where('target.litter_object_id', '!=', $desired->id)
                    ->orWhere('target.category_id', '!=', $this->targetCategoryId === null
                        ? DB::raw('source.category_id')
                        : $this->targetCategoryId)
                    ->orWhere(function ($q) {
                        $this->typeId === null
                            ? $q->whereNotNull('source.merged_into_type_id')
                            : $q->whereNull('source.merged_into_type_id')->orWhere('source.merged_into_type_id', '!=', $this->typeId);
                    });
            })
            ->exists();

        return !$disagreeing;
    }

    private function mappingIsValid(string $retiredKey, object $retired, string $desiredKey, object $desired): bool
    {
        // A pure category move keeps the object and only changes its shelf, so the two keys are
        // legitimately the same there. Without a target category it is a no-op mapping.
        if ((int) $retired->id === (int) $desired->id && $this->targetCategoryId === null) {
            $this->error('The retired and replacement tags must be different.');

            return false;
        }

        if ($desired->retired_at !== null) {
            $this->error("Replacement tag {$desiredKey} is already retired.");

            return false;
        }

        if ($retired->retired_at !== null && (int) $retired->merged_into_id !== (int) $desired->id) {
            $this->error("Tag {$retiredKey} already points to a different replacement.");

            return false;
        }

        // Repointing rows re-scores every tag, so a mapping across an XP boundary reaches the
        // leaderboard. That has to be an approved decision, never a side effect of a naming fix.
        if (XpScore::getObjectXp($retiredKey) !== XpScore::getObjectXp($desiredKey)
            && !$this->option('allow-xp-change')) {
            $this->error(sprintf(
                'The two tags have different XP values (%d → %d). Pass --allow-xp-change if the correction is approved.',
                XpScore::getObjectXp($retiredKey),
                XpScore::getObjectXp($desiredKey)
            ));

            return false;
        }

        if (DB::table('photo_tags')
            ->where('litter_object_id', $retired->id)
            ->whereNotNull('litter_object_type_id')
            ->exists()) {
            $this->error('Typed tags require a separate migration.');

            return false;
        }

        return true;
    }

    /**
     * v4 composite keys carry their subtype in the object name (`beer_can` = `can` + type
     * `beer`), so the split has to set a type as part of the same operation. Only the key is
     * resolved here; whether the type is approved for the survivor pairing is checked against
     * `category_object_types` once the survivor CLOs are known.
     */
    private function resolveType(): bool
    {
        $key = $this->option('type');

        if ($key === null || $key === '') {
            return true;
        }

        $id = DB::table('litter_object_types')->where('key', $key)->value('id');

        if ($id === null) {
            $this->error("Unknown litter object type: {$key}");

            return false;
        }

        $this->typeId = (int) $id;
        $this->typeKey = (string) $key;

        return true;
    }

    /**
     * Some approved mappings move the pairing to another category (`other/dump` becomes
     * `dumping/dumping`). Without an explicit target the run would repoint the object and leave
     * `category_id` alone, landing on a pairing nobody approved.
     */
    private function resolveTargetCategory(): bool
    {
        $key = $this->option('category');

        if ($key === null || $key === '') {
            return true;
        }

        $id = DB::table('categories')->where('key', $key)->value('id');

        if ($id === null) {
            $this->error("Unknown category: {$key}");

            return false;
        }

        $this->targetCategoryId = (int) $id;
        $this->targetCategoryKey = (string) $key;

        return true;
    }

    /**
     * Refuse a type the taxonomy has not approved for the survivor pairing. Attaching it here
     * would make a migration invent a taxonomy relationship, which is how the shadow objects
     * were created in the first place.
     *
     * @param array<int, int> $pivots category id => survivor CLO id
     */
    private function assertTypeIsApproved(array $pivots): void
    {
        if ($this->typeId === null) {
            return;
        }

        foreach ($pivots as $categoryId => $cloId) {
            $approved = DB::table('category_object_types')
                ->where('category_litter_object_id', $cloId)
                ->where('litter_object_type_id', $this->typeId)
                ->exists();

            if (!$approved) {
                throw new \RuntimeException(
                    "Type {$this->typeKey} is not approved for the replacement tag in category {$categoryId}."
                );
            }
        }
    }

    private function redisIsReachable(): bool
    {
        try {
            Redis::ping();

            return true;
        } catch (Throwable $e) {
            $this->error('Redis is unavailable: ' . $e->getMessage());

            return false;
        }
    }

    /** @return array{rows:int, tags:int, example_photo_ids:array<int, int>} */
    private function measure(int $retiredId): array
    {
        $query = DB::table('photo_tags')->where('litter_object_id', $retiredId);

        return [
            'rows' => (clone $query)->count(),
            'tags' => (int) (clone $query)->sum('quantity'),
            'example_photo_ids' => (clone $query)
                ->distinct()
                ->orderBy('photo_id')
                ->limit(5)
                ->pluck('photo_id')
                ->map('intval')
                ->all(),
        ];
    }

    /**
     * @param array{retired_key:string, retired_id:int, desired_key:string, desired_id:int} $entry
     * @param array{rows:int, tags:int, example_photo_ids:array<int, int>} $change
     */
    private function report(array $entry, array $change, bool $dryRun): void
    {
        $mode = $dryRun ? 'DRY RUN' : 'APPLY';

        $type = $this->typeKey === null ? '' : " + type {$this->typeKey}";
        $moved = $this->targetCategoryKey === null ? '' : " into category {$this->targetCategoryKey}";

        $this->line("{$mode}: {$entry['retired_key']} ({$entry['retired_id']}) → {$entry['desired_key']} ({$entry['desired_id']}){$type}{$moved}");
        $retiredXp = XpScore::getObjectXp($entry['retired_key']);
        $desiredXp = XpScore::getObjectXp($entry['desired_key']);

        if ($retiredXp !== $desiredXp) {
            $this->warn("XP per item: {$retiredXp} → {$desiredXp} — every affected tag is re-scored.");
        }

        $this->line("Rows: {$change['rows']}");
        $this->line("Tags: {$change['tags']}");
        $this->line('Example photo IDs: ' . ($change['example_photo_ids'] === []
            ? 'none'
            : implode(', ', $change['example_photo_ids'])));
    }

    /** @param array{retired_key:string, retired_id:int, desired_key:string, desired_id:int} $entry */
    private function apply(
        array $entry,
        int $totalRows,
        GeneratePhotoSummaryService $summaryService,
        MetricsService $metricsService,
    ): bool {
        $retiredId = (int) $entry['retired_id'];
        $desiredId = (int) $entry['desired_id'];
        $progress = $this->output->createProgressBar($totalRows);
        $progress->setFormat(' %current%/%max% rows [%bar%] %percent:3s%% %elapsed:6s%');
        $progress->start();

        try {
            $pivots = $this->prepareRetirement($retiredId, $desiredId);

            Photo::withTrashed()
                ->whereHas('photoTags', fn ($query) => $query->where('litter_object_id', $retiredId))
                ->chunkById(200, function ($photos) use ($retiredId, $desiredId, $pivots, $summaryService, $metricsService, $progress): void {
                    if (!$this->redisIsReachable()) {
                        throw new \RuntimeException('Redis became unavailable.');
                    }

                    $moved = DB::transaction(function () use ($photos, $retiredId, $desiredId, $pivots, $summaryService, $metricsService): int {
                        $photoIds = $photos->pluck('id');
                        $moved = 0;

                        foreach ($pivots as $categoryId => $desiredCloId) {
                            $update = [
                                'litter_object_id' => $desiredId,
                                'category_litter_object_id' => $desiredCloId,
                            ];

                            // Object and type move together: repointing a v4 composite key
                            // without its subtype would discard the distinction silently.
                            if ($this->typeId !== null) {
                                $update['litter_object_type_id'] = $this->typeId;
                            }

                            if ($this->targetCategoryId !== null) {
                                $update['category_id'] = $this->targetCategoryId;
                            }

                            $moved += DB::table('photo_tags')
                                ->whereIn('photo_id', $photoIds)
                                ->where('litter_object_id', $retiredId)
                                ->where('category_id', $categoryId)
                                ->update($update);
                        }

                        // Rows with no category still belong to the object: they take the same
                        // object and type, and land on the target pairing when the mapping moves.
                        $uncategorised = ['litter_object_id' => $desiredId];

                        if ($this->typeId !== null) {
                            $uncategorised['litter_object_type_id'] = $this->typeId;
                        }

                        if ($this->targetCategoryId !== null) {
                            $uncategorised['category_id'] = $this->targetCategoryId;
                            $uncategorised['category_litter_object_id'] = $pivots[$this->targetCategoryId]
                                ?? CategoryObject::resolveId($this->targetCategoryId, $desiredId);
                        }

                        $moved += DB::table('photo_tags')
                            ->whereIn('photo_id', $photoIds)
                            ->where('litter_object_id', $retiredId)
                            ->whereNull('category_id')
                            ->update($uncategorised);

                        $photos->load([
                            'photoTags.category',
                            'photoTags.object',
                            'photoTags.type',
                            'photoTags.extraTags.extraTag',
                        ]);

                        foreach ($photos as $photo) {
                            $summaryService->run($photo);

                            if ($photo->processed_at !== null && $photo->deleted_at === null) {
                                $metricsService->processPhoto($photo);
                            }
                        }

                        return $moved;
                    });

                    $progress->advance($moved);
                });
        } catch (Throwable $e) {
            $this->newLine();
            $this->error('Migration stopped: ' . $e->getMessage());

            return false;
        }

        $progress->finish();
        $this->newLine();

        return true;
    }

    /**
     * Every validation the apply performs, with no writes, so a dry run rehearses the same checks:
     * survivor pairing declared and active, immutable recorded mappings, no interrupted earlier
     * mapping left with rows, approved type. Throws RuntimeException on the first failure.
     *
     * @return array{
     *     pivots: array<int, int>,
     *     tombstones: array<int, array{retired_clo_id: int, desired_clo_id: int}>,
     *     backfills: array<int, array{category_id: int, desired_clo_id: int}>
     * } pivots: category id => survivor CLO id
     */
    private function planRetirement(int $retiredId, int $desiredId): array
    {
        $categoryIds = DB::table('category_litter_object')
            ->where('litter_object_id', $retiredId)
            ->pluck('category_id')
            ->merge(DB::table('photo_tags')
                ->where('litter_object_id', $retiredId)
                ->whereNotNull('category_id')
                ->pluck('category_id'))
            ->unique()
            ->map('intval');
        $pivots = [];
        $tombstones = [];
        $backfills = [];

        foreach ($categoryIds as $categoryId) {
            $retiredClo = DB::table('category_litter_object')
                ->where('category_id', $categoryId)
                ->where('litter_object_id', $retiredId)
                ->first(['id', 'merged_into_clo_id', 'merged_into_type_id']);

            // A category move lands on a fixed target pairing; otherwise the survivor stays
            // in the category the rows are already in.
            $targetCategoryId = $this->targetCategoryId ?? $categoryId;

            // A recorded mapping is immutable. A redirect recorded by an earlier, different
            // mapping is skipped — its chain continues through the survivor it recorded. A
            // redirect from this same mapping is resumed. One that disagrees with the
            // requested category or type is a conflicting retry and aborts the run.
            if ($retiredClo?->merged_into_clo_id !== null) {
                $recorded = DB::table('category_litter_object')
                    ->where('id', $retiredClo->merged_into_clo_id)
                    ->first(['id', 'category_id', 'litter_object_id']);

                if ((int) $recorded->litter_object_id !== $desiredId) {
                    // Only a finished mapping may be skipped. Rows or presets still on the
                    // pairing mean that mapping stopped part-way; retiring the object now
                    // would strand them and make the earlier mapping impossible to re-run.
                    $rowsLeft = DB::table('photo_tags')
                        ->where('category_id', $categoryId)
                        ->where('litter_object_id', $retiredId)
                        ->count();
                    $quickTagsLeft = DB::table('user_quick_tags')->where('clo_id', $retiredClo->id)->count();

                    if ($rowsLeft > 0 || $quickTagsLeft > 0) {
                        throw new \RuntimeException(sprintf(
                            'Pairing %d was mapped into pivot %d by an earlier mapping, but %d row(s) and %d quick tag(s) remain on it; re-run that mapping first.',
                            $retiredClo->id,
                            $recorded->id,
                            $rowsLeft,
                            $quickTagsLeft
                        ));
                    }

                    continue;
                }

                $recordedTypeId = $retiredClo->merged_into_type_id === null ? null : (int) $retiredClo->merged_into_type_id;

                if ((int) $recorded->category_id !== $targetCategoryId || $recordedTypeId !== $this->typeId) {
                    throw new \RuntimeException(sprintf(
                        'Pairing %d is already mapped to pivot %d (category %d, type %s); recorded mappings are immutable.',
                        $retiredClo->id,
                        $recorded->id,
                        $recorded->category_id,
                        $recordedTypeId ?? 'none'
                    ));
                }
            }

            $desiredClo = DB::table('category_litter_object')
                ->where('category_id', $targetCategoryId)
                ->where('litter_object_id', $desiredId)
                ->first(['id', 'merged_into_clo_id']);
            $categoryKey = $this->targetCategoryKey
                ?? (string) DB::table('categories')->where('id', $categoryId)->value('key');

            // The survivor pairing can itself have been retired by an earlier category move.
            // Rows landed on a retired pairing pass the existence check and are stranded.
            if ($desiredClo?->merged_into_clo_id !== null) {
                throw new \RuntimeException(sprintf(
                    'The replacement pairing in category %s is retired (pivot %d moved into pivot %d); map onto the active pairing instead.',
                    $categoryKey,
                    $desiredClo->id,
                    $desiredClo->merged_into_clo_id
                ));
            }

            if ($desiredClo === null) {
                // Taxonomy is declared in TagsConfig and created by the seeder. A migration
                // that invents the survivor pairing is how the shadow objects were made.
                throw new \RuntimeException(
                    "No approved pivot for the replacement tag in category {$categoryKey}. "
                    . 'Declare the pairing in TagsConfig and run the seeder, or move the rows with --category.'
                );
            }

            $desiredCloId = (int) $desiredClo->id;
            $pivots[$categoryId] = $desiredCloId;
            $backfills[] = ['category_id' => $targetCategoryId, 'desired_clo_id' => $desiredCloId];

            if ($retiredClo !== null && (int) $retiredClo->id !== $desiredCloId && $retiredClo->merged_into_clo_id === null) {
                $tombstones[] = ['retired_clo_id' => (int) $retiredClo->id, 'desired_clo_id' => $desiredCloId];
            }
        }

        $this->assertTypeIsApproved($pivots);

        return ['pivots' => $pivots, 'tombstones' => $tombstones, 'backfills' => $backfills];
    }

    /** @return array<int, int> category id => replacement CLO id */
    private function prepareRetirement(int $retiredId, int $desiredId): array
    {
        return DB::transaction(function () use ($retiredId, $desiredId): array {
            $plan = $this->planRetirement($retiredId, $desiredId);

            // Only a mapping that replaces the object retires it. A category move keeps the same
            // object in active use on a different shelf.
            if ($retiredId !== $desiredId) {
                DB::table('litter_objects')
                    ->where('id', $retiredId)
                    ->update([
                        'retired_at' => now(),
                        'merged_into_id' => $desiredId,
                        'updated_at' => now(),
                    ]);
            }

            // Rows written onto the desired object before it had a pivot carry a null CLO.
            // They never reference the retired object, so the per-photo loop cannot reach
            // them; quick tags and team tag editing follow the stored pointer and skip them
            // until it is set. Matched on the target category so the backfilled pointer
            // always agrees with the row's own pairing.
            foreach ($plan['backfills'] as $backfill) {
                DB::table('photo_tags')
                    ->where('category_id', $backfill['category_id'])
                    ->where('litter_object_id', $desiredId)
                    ->whereNull('category_litter_object_id')
                    ->update(['category_litter_object_id' => $backfill['desired_clo_id']]);
            }

            // The approved mapping is a triple — survivor object, category and type — and a
            // retirement can span categories with a different survivor in each, so it is
            // recorded on the source pivot, not the object. Stale clients and saved quick tags
            // resolve the exact pairing and subtype from here.
            foreach ($plan['tombstones'] as $tombstone) {
                DB::table('category_litter_object')
                    ->where('id', $tombstone['retired_clo_id'])
                    ->update([
                        'merged_into_clo_id' => $tombstone['desired_clo_id'],
                        'merged_into_type_id' => $this->typeId,
                        'updated_at' => now(),
                    ]);

                $quickTagUpdate = ['clo_id' => $tombstone['desired_clo_id'], 'updated_at' => now()];

                if ($this->typeId !== null) {
                    $quickTagUpdate['type_id'] = $this->typeId;
                }

                DB::table('user_quick_tags')
                    ->where('clo_id', $tombstone['retired_clo_id'])
                    ->update($quickTagUpdate);
            }

            // Summaries are regenerated later in this same process and derive the CLO from
            // (category_id, litter_object_id); a map memoised earlier could be stale.
            CategoryObject::flushResolverCache();

            return $plan['pivots'];
        });
    }
}
