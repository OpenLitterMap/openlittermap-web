<?php

declare(strict_types=1);

namespace App\Console\Commands\Tags;

use App\Enums\XpScore;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CategoryObject;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\LitterObjectType;
use App\Models\Photo;
use App\Services\Metrics\MetricsService;
use App\Services\Tags\GeneratePhotoSummaryService;
use App\Tags\TagsConfig;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * - Retire one object and update its observations, summaries, metrics and saved quick tags.
 * - Example: plasticBags → plastic_bag keeps the category "other".
 * - The old object does not need a CLO; its replacement must have one in each used category.
 * - Run with writes paused. Default is a read-only preview; --apply writes in batches.
 */
class MigrateTag extends Command
{
    public const LOCK_NAME = 'olm:migrate-tag';

    protected $signature = 'olm:migrate-tag
        {old : Object key to retire}
        {new : Replacement object key}
        {--type= : Approved replacement type key}
        {--create-destination : Create missing destination CLOs declared in TagsConfig}
        {--allow-xp-change : Allow objects with different per-item XP}
        {--apply : Apply the migration; default is a read-only preview}';

    protected $description = 'Retire an object and migrate its data to its replacement.';

    public function handle(GeneratePhotoSummaryService $summaries, MetricsService $metrics): int
    {
        $locked = false;
        try {
            if (! Schema::hasColumns('litter_objects', ['retired_at', 'merged_into_id', 'merged_into_type_id'])) {
                throw new RuntimeException('Apply the pending object-retirement schema migrations first.');
            }
            if ($this->option('apply')) {
                $locked = (int) DB::selectOne('SELECT GET_LOCK(?, 0) AS acquired', [self::LOCK_NAME])->acquired === 1;
                if (! $locked) {
                    throw new RuntimeException('Another tag migration is running.');
                }
            }

            $source = LitterObject::where('key', $this->argument('old'))->first();
            $destination = LitterObject::where('key', $this->argument('new'))->first();
            if (! $source || ! $destination || $source->id === $destination->id) {
                throw new RuntimeException('Choose two different existing object keys.');
            }
            $type = $this->option('type') === null ? null : LitterObjectType::where('key', $this->option('type'))->first();
            if ($this->option('type') !== null && $type === null) {
                throw new RuntimeException('Unknown replacement type.');
            }
            $typeId = $type?->id;
            $plan = $this->plan($source, $destination, $typeId);
            $this->report($source, $destination, $type, $plan);
            if ($plan === [] && $source->retired_at !== null) {
                $this->info('Already applied: no source observations or quick tags remain.');
                if ($this->option('apply')) {
                    $this->reportCompletion($source, $destination, $type, 0, 0, 0, 0);
                }
                return self::SUCCESS;
            }
            if (! $this->option('apply')) {
                return self::SUCCESS;
            }

            $this->checkConnections();
            [$destinations, $quickTagsMoved] = DB::transaction(function () use ($source, $destination, $typeId) {
                $objects = LitterObject::whereIn('id', [$source->id, $destination->id])
                    ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $source = $objects[$source->id];
                $destination = $objects[$destination->id];
                $plan = $this->plan($source, $destination, $typeId);
                $destinations = [];
                foreach ($plan as $categoryId => $entry) {
                    $cloId = $entry['clo_id'];
                    if ($cloId === null) {
                        $cloId = CategoryObject::create([
                            'category_id' => $categoryId, 'litter_object_id' => $destination->id,
                        ])->id;
                        foreach ($entry['create_types'] as $id) {
                            DB::table('category_object_types')->insert([
                                'category_litter_object_id' => $cloId, 'litter_object_type_id' => $id,
                            ]);
                        }
                    }
                    $destinations[$categoryId] = $cloId;
                }
                $source->update([
                    'retired_at' => $source->retired_at ?? now(),
                    'merged_into_id' => $destination->id,
                    'merged_into_type_id' => $typeId,
                ]);
                $quickTagsMoved = 0;
                foreach ($destinations as $categoryId => $cloId) {
                    $values = ['clo_id' => $cloId];
                    if ($typeId !== null) {
                        $values['type_id'] = $typeId;
                    }
                    $quickTagsMoved += DB::table('user_quick_tags')->whereIn('clo_id',
                        DB::table('category_litter_object')->where('litter_object_id', $source->id)
                            ->where('category_id', $categoryId)->select('id')
                    )->update($values);
                }
                return [$destinations, $quickTagsMoved];
            });

            $moved = 0;
            $quantityMoved = 0;
            $photosUpdated = 0;
            $totalRows = $this->sourceRows($source->id)->count();
            if ($totalRows > 0) {
                $this->line('Progress: 0.0% (0/'.number_format($totalRows).' photo-tag records committed this run).');
            }
            while (true) {
                $this->checkConnections();
                $photoIds = $this->sourceRows($source->id)->select('photo_id')->distinct()
                    ->orderBy('photo_id')->limit(200)->pluck('photo_id');
                if ($photoIds->isEmpty()) {
                    break;
                }
                $started = microtime(true);
                [$count, $quantity, $photoCount] = DB::transaction(function () use ($photoIds, $source, $destination, $typeId, $destinations, $summaries, $metrics) {
                    $photos = Photo::withTrashed()->whereIn('id', $photoIds)->orderBy('id')->lockForUpdate()->get();
                    $quantity = (int) $this->sourceRows($source->id)->whereIn('photo_id', $photos->modelKeys())->sum('quantity');
                    $count = 0;
                    foreach ($photos as $photo) {
                        foreach ($destinations as $categoryId => $cloId) {
                            $count += $this->sourceRows($source->id)->where('photo_id', $photo->id)
                                ->where('category_id', $categoryId)->update([
                                    'litter_object_id' => $destination->id,
                                    'category_litter_object_id' => $cloId,
                                    'litter_object_type_id' => $typeId,
                                ]);
                        }
                        $summaries->run($photo);
                        if ($photo->processed_at !== null && ! $photo->trashed()) {
                            $metrics->processPhoto($photo);
                        }
                    }
                    return [$count, $quantity, $photos->count()];
                });
                if ($count === 0) {
                    throw new RuntimeException('Source rows remain but the batch made no progress. Keep writes paused and inspect.');
                }
                $moved += $count;
                $quantityMoved += $quantity;
                $photosUpdated += $photoCount;
                // - Advance only after commit; do not round unfinished work up to 100%.
                $percent = floor($moved / max(1, $totalRows) * 1000) / 10;
                $this->line(sprintf(
                    'Progress: %.1f%% (%s/%s photo-tag records committed this run). Batch: %d photos, %.2fs.',
                    $percent, number_format($moved), number_format($totalRows), $photoCount, microtime(true) - $started
                ));
            }
            $this->info('Migration complete.');
            $this->reportCompletion($source, $destination, $type, $moved, $quantityMoved, $photosUpdated, $quickTagsMoved);
            $this->warn('Verify summaries, XP, exports and Redis deltas before reopening writes. These counts do not verify those checks.');
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            if ($this->option('apply')) {
                $this->warn('Keep writes paused. Inspect the failure and resume with the same arguments; Redis may need rebuilding.');
            }
            return self::FAILURE;
        } finally {
            if ($locked) {
                DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [self::LOCK_NAME]);
            }
        }
    }

    /** - Build the same read-only checks for preview and the retirement transaction. */
    private function plan(LitterObject $source, LitterObject $destination, ?int $typeId): array
    {
        if ($source->retired_at !== null) {
            if ($source->merged_into_id !== $destination->id || $source->merged_into_type_id !== $typeId) {
                throw new RuntimeException('The recorded replacement or type disagrees with these arguments.');
            }
            if (! $this->sourceRows($source->id)->exists() && ! $this->quickTags($source->id)->exists()) {
                return [];
            }
        }
        if ($destination->retired_at !== null) {
            throw new RuntimeException('The destination object is retired.');
        }
        foreach (LitterObject::where('merged_into_id', $source->id)->get() as $previous) {
            if ($this->sourceRows($previous->id)->exists() || $this->quickTags($previous->id)->exists()) {
                throw new RuntimeException("Finish {$previous->key} → {$source->key} before starting this mapping.");
            }
        }
        if ($this->sourceRows($source->id)->whereNull('category_id')->exists()) {
            throw new RuntimeException('Source observations have no category. Resolve those before migrating.');
        }
        if ($this->sourceRows($source->id)->whereNotNull('litter_object_type_id')->exists()) {
            throw new RuntimeException('Already-typed source observations require a separately reviewed migration.');
        }
        if (XpScore::getObjectXp($source->key) !== XpScore::getObjectXp($destination->key)
            && ! $this->option('allow-xp-change')) {
            throw new RuntimeException('Per-item object XP differs. Review and use --allow-xp-change if approved.');
        }

        $categories = $this->sourceRows($source->id)->distinct()->pluck('category_id')
            ->merge($this->quickTags($source->id)->distinct()->pluck('clo.category_id'))->unique();
        $plan = [];
        foreach ($categories as $categoryId) {
            $category = Category::findOrFail($categoryId);
            $clo = CategoryObject::where('category_id', $categoryId)->where('litter_object_id', $destination->id)->first();
            $createTypes = [];
            if ($clo === null) {
                if (! $this->option('create-destination')) {
                    throw new RuntimeException("Missing destination CLO: {$category->key}/{$destination->key}. Use --create-destination only after review.");
                }
                $config = TagsConfig::get()[$category->key] ?? [];
                if (! array_key_exists($destination->key, $config)) {
                    throw new RuntimeException("Destination {$category->key}/{$destination->key} is not declared in TagsConfig.");
                }
                foreach ($config[$destination->key]['types'] ?? [] as $key) {
                    $id = LitterObjectType::where('key', $key)->value('id');
                    if ($id === null) {
                        throw new RuntimeException("Declared type {$key} is missing; no types will be created.");
                    }
                    $createTypes[] = (int) $id;
                }
            }
            $allowed = $clo ? $clo->types()->pluck('litter_object_types.id')->all() : $createTypes;
            $quickTypes = $this->quickTags($source->id)->where('clo.category_id', $categoryId)->whereNotNull('qt.type_id')->distinct()->pluck('qt.type_id')->all();
            $required = $typeId === null ? $quickTypes : [$typeId];
            if (array_diff($required, $allowed)) {
                $ids = $this->quickTags($source->id)->where('clo.category_id', $categoryId)
                    ->whereNotNull('qt.type_id')->whereNotIn('qt.type_id', $allowed)->pluck('qt.id')->implode(', ');
                throw new RuntimeException("Incompatible destination or quick-tag type for {$category->key}/{$destination->key}. Quick tag IDs: {$ids}");
            }
            $plan[(int) $categoryId] = ['clo_id' => $clo?->id, 'category' => $category->key, 'create_types' => $createTypes];
        }
        return $plan;
    }

    private function sourceRows(int $objectId): Builder
    {
        return DB::table('photo_tags')->where('litter_object_id', $objectId);
    }

    private function quickTags(int $objectId): Builder
    {
        return DB::table('user_quick_tags as qt')->join('category_litter_object as clo', 'clo.id', '=', 'qt.clo_id')
            ->where('clo.litter_object_id', $objectId);
    }

    private function report(LitterObject $source, LitterObject $destination, ?LitterObjectType $type, array $plan): void
    {
        $mode = $this->option('apply') ? 'APPLY' : 'DRY RUN';
        $this->info("{$mode}: {$source->key} ({$source->id}) → {$destination->key} ({$destination->id})");
        if ($source->retired_at !== null && $plan !== []) {
            $this->line('Resume: matching retirement is recorded; processing remaining rows.');
        }
        $this->line('Rows: '.$this->sourceRows($source->id)->count());
        $this->line('Photos: '.$this->sourceRows($source->id)->distinct()->count('photo_id'));
        $this->line('Quantity: '.$this->sourceRows($source->id)->sum('quantity'));
        $this->line('Example photo IDs: '.$this->sourceRows($source->id)->select('photo_id')->distinct()->orderBy('photo_id')->limit(5)->pluck('photo_id')->implode(', '));
        $this->line('Quick tags to repoint: '.$this->quickTags($source->id)->count());
        foreach ($this->quickTags($source->id)->select('qt.id', 'qt.clo_id', 'qt.type_id', 'clo.category_id')->get() as $quick) {
            $destinationClo = $plan[$quick->category_id]['clo_id'] ?? 'new destination CLO';
            $destinationType = $type?->id ?? $quick->type_id ?? 'none';
            $this->line("Quick tag {$quick->id}: CLO {$quick->clo_id} → {$destinationClo}, type {$destinationType}.");
        }
        $this->line('Per-item object XP delta: '.(XpScore::getObjectXp($destination->key) - XpScore::getObjectXp($source->key)));
        $this->line('Whole-photo XP is recalculated using current rules; the per-item delta does not predict that total.');
        $this->line('Replacement type: '.($type?->key ?? 'none'));
        foreach ($plan as $categoryId => $entry) {
            if ($entry['clo_id'] === null) {
                $this->line("Would create category_litter_object: category_id={$categoryId}, litter_object_id={$destination->id} ({$entry['category']}/{$destination->key}).");
                foreach ($entry['create_types'] as $id) {
                    $this->line("Would create category_object_types: new destination CLO, litter_object_type_id={$id}.");
                }
            } else {
                $this->line("Destination: {$entry['category']}/{$destination->key}, CLO {$entry['clo_id']}.");
            }
        }
        $empty = CategoryObject::where('litter_object_id', $source->id)->whereNotIn('category_id', array_keys($plan))->count();
        $this->line("Unused source CLOs: {$empty} (informational; no destination required).");
    }

    /**
     * - Report committed work from this invocation, including a resumed run.
     * - Destination totals include observations that existed before this run.
     */
    private function reportCompletion(LitterObject $source, LitterObject $destination, ?LitterObjectType $type, int $rows, int $quantity, int $photos, int $quickTags): void
    {
        $this->newLine();
        $this->info("Post-migration summary: {$source->key} → {$destination->key}");
        $this->line('Replacement type: '.($type?->key ?? 'none'));
        $this->line('Photo-tag records migrated this run: '.number_format($rows));
        $this->line('Total quantity migrated this run: '.number_format($quantity));
        $this->line('Photos with summaries regenerated this run: '.number_format($photos));
        $this->line('Saved quick tags repointed this run: '.number_format($quickTags));
        $this->line('Source photo-tag records remaining: '.number_format($this->sourceRows($source->id)->count()));
        $this->line('Source quick tags remaining: '.number_format($this->quickTags($source->id)->count()));
        $this->line("Current {$destination->key} totals (all categories/types, including existing records): "
            .number_format($this->sourceRows($destination->id)->count()).' photo-tag records, '
            .number_format((int) $this->sourceRows($destination->id)->sum('quantity')).' total quantity.');
        $this->line('Source object is retired; its recorded replacement remains available for old submissions.');
    }

    private function checkConnections(): void
    {
        $lock = DB::selectOne('SELECT IS_USED_LOCK(?) AS owner, CONNECTION_ID() AS connection_id', [self::LOCK_NAME]);
        if ($lock->owner === null || (int) $lock->owner !== (int) $lock->connection_id) {
            throw new RuntimeException('The migration database lock was lost.');
        }
        if (Redis::connection()->ping() === false) {
            throw new RuntimeException('Redis is unreachable.');
        }
    }
}
