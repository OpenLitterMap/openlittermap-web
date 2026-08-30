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
            return self::SUCCESS;
        }

        if (!$this->redisIsReachable()) {
            return self::FAILURE;
        }

        return $this->apply($entry, $change['rows'], $summaryService, $metricsService)
            ? self::SUCCESS
            : self::FAILURE;
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

                        $moved += DB::table('photo_tags')
                            ->whereIn('photo_id', $photoIds)
                            ->where('litter_object_id', $retiredId)
                            ->whereNull('category_id')
                            ->update(['litter_object_id' => $desiredId]);

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

    /** @return array<int, int> category id => replacement CLO id */
    private function prepareRetirement(int $retiredId, int $desiredId): array
    {
        return DB::transaction(function () use ($retiredId, $desiredId): array {
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

            foreach ($categoryIds as $categoryId) {
                $retiredCloId = DB::table('category_litter_object')
                    ->where('category_id', $categoryId)
                    ->where('litter_object_id', $retiredId)
                    ->value('id');

                // A category move lands on a fixed target pairing; otherwise the survivor stays
                // in the category the rows are already in.
                $targetCategoryId = $this->targetCategoryId ?? $categoryId;

                $desiredCloId = DB::table('category_litter_object')
                    ->where('category_id', $targetCategoryId)
                    ->where('litter_object_id', $desiredId)
                    ->value('id');

                if ($desiredCloId === null) {
                    // Creating the survivor pivot in place is approved; creating one for a
                    // category the mapping moves to would be the migration choosing taxonomy.
                    if ($this->targetCategoryId !== null) {
                        throw new \RuntimeException(
                            "No approved pivot for the replacement tag in category {$this->targetCategoryKey}."
                        );
                    }

                    $desiredCloId = DB::table('category_litter_object')->insertGetId([
                        'category_id' => $categoryId,
                        'litter_object_id' => $desiredId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $pivots[$categoryId] = (int) $desiredCloId;

                // Rows written onto the desired object before it had a pivot carry a null CLO.
                // They never reference the retired object, so the per-photo loop below cannot
                // reach them; quick tags and team tag editing follow the stored pointer and
                // skip them until it is set. Matched on the target category so the backfilled
                // pointer always agrees with the row's own pairing.
                DB::table('photo_tags')
                    ->where('category_id', $targetCategoryId)
                    ->where('litter_object_id', $desiredId)
                    ->whereNull('category_litter_object_id')
                    ->update(['category_litter_object_id' => $desiredCloId]);

                if ($retiredCloId !== null) {
                    DB::table('user_quick_tags')
                        ->where('clo_id', $retiredCloId)
                        ->update(['clo_id' => $desiredCloId, 'updated_at' => now()]);
                }
            }

            $this->assertTypeIsApproved($pivots);

            // Summaries are regenerated later in this same process and derive the CLO from
            // (category_id, litter_object_id). A map memoised before the inserts above would
            // still answer "no pivot" for the survivor.
            CategoryObject::flushResolverCache();

            return $pivots;
        });
    }
}
