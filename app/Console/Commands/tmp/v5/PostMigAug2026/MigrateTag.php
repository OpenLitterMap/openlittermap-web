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
 * - Apply one approved object or category change.
 * - Example: plasticBags → plastic_bag retires the old object.
 * - A category-only move retires the old CLO and keeps the object active.
 * - Dry-run by default; --apply writes the changes.
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

    /** - Type ID to set on migrated tags, e.g. beer for beer_can → can. */
    private ?int $typeId = null;

    private ?string $typeKey = null;

    /** - Target category ID for a category move, e.g. --category=dumping. */
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
            // - Run the same mapping checks in dry-run and --apply.
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
     * - Return true only when this object retirement has finished as requested.
     * - No photo tags or quick tags may remain on the retired object or its old CLOs.
     * - Keep redirects recorded by earlier category moves.
     * - Example: after A → B and B → C finish, running A → B again does nothing.
     */
    private function alreadyApplied(object $retired, object $desired): bool
    {
        if ($retired->retired_at === null
            || (int) $retired->id === (int) $desired->id
            || (int) $retired->merged_into_id !== (int) $desired->id) {
            return false;
        }

        $sources = $this->sourceClos((int) $retired->id);
        $rowsLeft = DB::table('photo_tags')->where('litter_object_id', $retired->id)->exists();
        $pivotsLeft = $sources->isEmpty() || $sources->contains(fn (object $clo) => $clo->merged_into_clo_id === null);
        $quickTagsLeft = DB::table('user_quick_tags as uqt')
            ->join('category_litter_object as clo', 'clo.id', '=', 'uqt.clo_id')
            ->where('clo.litter_object_id', $retired->id)
            ->exists();

        if ($rowsLeft || $pivotsLeft || $quickTagsLeft) {
            return false;
        }

        // Earlier category moves keep their redirects. Check them by the same rule as planning.
        foreach ($sources as $source) {
            $target = DB::table('category_litter_object')->where('id', $source->merged_into_clo_id)->first();
            if (! $this->recordedMappingAgrees($source, $target, (int) $desired->id, (int) $source->category_id)) {
                return false;
            }
        }

        return true;
    }

    /** @return \Illuminate\Support\Collection<int, object> every CLO row of the retired object */
    private function sourceClos(int $objectId): \Illuminate\Support\Collection
    {
        return DB::table('category_litter_object')->where('litter_object_id', $objectId)->get();
    }

    /**
     * - Keep an earlier redirect to a different object.
     * - For this object retirement, the recorded category and type must match the request.
     * - Example: repeating a mapping with --type=wine after --type=beer is a conflict.
     * - A missing replacement CLO never counts as a completed mapping.
     */
    private function recordedMappingAgrees(object $source, ?object $target, int $desiredId, int $categoryId): bool
    {
        if ($target === null) {
            return false;
        }
        if ((int) $target->litter_object_id !== $desiredId) {
            return true;
        }

        $recordedTypeId = $source->merged_into_type_id === null ? null : (int) $source->merged_into_type_id;

        return (int) $target->category_id === ($this->targetCategoryId ?? $categoryId)
            && $recordedTypeId === $this->typeId;
    }

    private function mappingIsValid(string $retiredKey, object $retired, string $desiredKey, object $desired): bool
    {
        // - The object keys can match when --category moves the object to another category.
        // - Without --category, matching keys would change nothing.
        if ((int) $retired->id === (int) $desired->id && $this->targetCategoryId === null) {
            $this->error('The retired and replacement tags must be different.');

            return false;
        }

        if ($desired->retired_at !== null) {
            $this->error("Replacement tag {$desiredKey} is already retired.");

            return false;
        }

        // - A retired object whose CLO redirects are incomplete cannot prove what an earlier run recorded.
        if ($retired->retired_at !== null
            && $this->sourceClos((int) $retired->id)->contains(fn (object $clo) => $clo->merged_into_clo_id === null)) {
            $this->error('The retired object has incomplete CLO redirects; this retry cannot be verified.');
            return false;
        }

        if ($retired->retired_at !== null && (int) $retired->merged_into_id !== (int) $desired->id) {
            $this->error("Tag {$retiredKey} already points to a different replacement.");

            return false;
        }

        // - Moving tags recalculates their XP and can change leaderboard totals.
        // - Require --allow-xp-change when the old and new objects have different XP values.
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
     * - Look up the litter_object_type_id requested by --type.
     * - Example: beer_can → can with --type=beer keeps the beer information.
     * - Check that the type is allowed on the replacement CLOs in assertTypeIsApproved().
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
     * - Look up the category requested by --category.
     * - Example: other/dump → dumping/dumping needs --category=dumping.
     * - Without this option, tags stay in their current categories.
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
     * - Require the requested type to exist in category_object_types for every replacement CLO.
     * - Example: --type=beer requires beer to be allowed on the replacement can CLO.
     * - Do not add allowed types while moving photo tags.
     *
     * @param array<int, int> $pivots category ID => replacement CLO ID
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

                            // - Move object and type together, e.g. beer_can → can with type beer.
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

                        // - Tags with no category still receive the replacement object and type.
                        // - Set the target category and CLO when --category is supplied.
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
     * - Check the mapping without changing data; dry-run and --apply use these checks.
     * - Require active replacement CLOs and an allowed type when --type is supplied.
     * - Refuse conflicts with recorded redirects and unfinished earlier mappings.
     * - Example: if tags remain on an earlier retired CLO, finish that mapping first.
     * - Throw RuntimeException on the first failure.
     *
     * @return array{
     *     pivots: array<int, int>,
     *     tombstones: array<int, array{retired_clo_id: int, desired_clo_id: int}>,
     *     backfills: array<int, array{category_id: int, desired_clo_id: int}>
     * } pivots: category ID => replacement CLO ID
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
        if ($categoryIds->isEmpty() || DB::table('photo_tags')->where('litter_object_id', $retiredId)->whereNull('category_id')->exists()) {
            throw new \RuntimeException('Source categories cannot be verified. Prepare historical CLOs before migrating.');
        }
        $pivots = [];
        $tombstones = [];
        $backfills = [];

        foreach ($categoryIds as $categoryId) {
            $retiredClo = DB::table('category_litter_object')
                ->where('category_id', $categoryId)
                ->where('litter_object_id', $retiredId)
                ->first(['id', 'merged_into_clo_id', 'merged_into_type_id']);

            if ($retiredClo === null) {
                throw new \RuntimeException('Missing source CLO. Declare the historical combination in TagsConfig and run GenerateTagsSeeder first.');
            }

            // - Use --category when supplied; otherwise keep the tag's current category.
            $targetCategoryId = $this->targetCategoryId ?? $categoryId;

            // - Keep redirects from earlier mappings.
            // - Resume this mapping only if its recorded category and type match.
            // - Example: a recorded beer type cannot be replaced with wine by rerunning the command.
            if ($retiredClo?->merged_into_clo_id !== null) {
                $recorded = DB::table('category_litter_object')
                    ->where('id', $retiredClo->merged_into_clo_id)
                    ->first(['id', 'category_id', 'litter_object_id']);

                if (! $this->recordedMappingAgrees($retiredClo, $recorded, $desiredId, $categoryId)) {
                    throw new \RuntimeException(sprintf(
                        'Pairing %d is already mapped to a missing or conflicting destination; recorded mappings are immutable.',
                        $retiredClo->id
                    ));
                }

                if ((int) $recorded->litter_object_id !== $desiredId) {
                    // - Skip an earlier mapping only after its photo tags and quick tags have moved.
                    // - If any remain on its old CLO, require that mapping to finish first.
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
            }

            $desiredClo = DB::table('category_litter_object')
                ->where('category_id', $targetCategoryId)
                ->where('litter_object_id', $desiredId)
                ->first(['id', 'merged_into_clo_id', 'is_selectable']);
            $categoryKey = $this->targetCategoryKey
                ?? (string) DB::table('categories')->where('id', $categoryId)->value('key');

            // - The replacement CLO must be active, not just present.
            // - Example: an old other CLO may already redirect to dumping.
            if ($desiredClo?->merged_into_clo_id !== null) {
                throw new \RuntimeException(sprintf(
                    'The replacement pairing in category %s is retired (pivot %d moved into pivot %d); map onto the active pairing instead.',
                    $categoryKey,
                    $desiredClo->id,
                    $desiredClo->merged_into_clo_id
                ));
            }

            if ($desiredClo === null) {
                // - Declare the replacement CLO in TagsConfig and create it with the tags seeder.
                // - This command moves tags; it does not create missing CLOs.
                throw new \RuntimeException(
                    "No approved pivot for the replacement tag in category {$categoryKey}. "
                    . 'Declare the pairing in TagsConfig and run the seeder, or move the rows with --category.'
                );
            }

            if (! $desiredClo->is_selectable) {
                throw new \RuntimeException('The replacement CLO is historical and not selectable. Choose an approved current replacement.');
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

            // - Retire the object only when its replacement is a different object.
            // - A category-only move keeps the object active and retires its old CLO.
            if ($retiredId !== $desiredId) {
                DB::table('litter_objects')
                    ->where('id', $retiredId)
                    ->update([
                        'retired_at' => now(),
                        'merged_into_id' => $desiredId,
                        'updated_at' => now(),
                    ]);
            }

            // - Fill missing stored CLO IDs on photo tags already using the replacement object.
            // - Match both category_id and litter_object_id; leave those source fields unchanged.
            // - This updates the deprecated stored ID; it does not move these photo tags.
            foreach ($plan['backfills'] as $backfill) {
                DB::table('photo_tags')
                    ->where('category_id', $backfill['category_id'])
                    ->where('litter_object_id', $desiredId)
                    ->whereNull('category_litter_object_id')
                    ->update(['category_litter_object_id' => $backfill['desired_clo_id']]);
            }

            // - Record the replacement CLO ID and optional type on each old CLO.
            // - Example: beer_can's old CLO points to can's CLO with merged_into_type_id = beer's ID.
            // - Requests using the old CLO ID and saved quick tags follow this redirect.
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

            // - Clear cached CLO IDs before regenerating summaries from category_id and litter_object_id.
            CategoryObject::flushResolverCache();

            return $plan['pivots'];
        });
    }
}
