<?php

declare(strict_types=1);

namespace App\Console\Commands\tmp\v5\PostMigAug2026;

use App\Enums\XpScore;
use App\Models\Photo;
use App\Services\Metrics\MetricsService;
use App\Services\Tags\GeneratePhotoSummaryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

/** Retires one approved litter object in favour of another. */
class MigrateTag extends Command
{
    protected $signature = 'olm:migrate-tag
        {retired : litter object key to retire}
        {desired : replacement litter object key}
        {--apply : execute the migration (dry-run by default)}';

    protected $description = 'Retire one litter object and move its data to another.';

    public function handle(GeneratePhotoSummaryService $summaries, MetricsService $metrics): int
    {
        $retiredKey = (string) $this->argument('retired');
        $desiredKey = (string) $this->argument('desired');
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

        return $this->apply($entry, $summaries, $metrics)
            ? self::SUCCESS
            : self::FAILURE;
    }

    private function mappingIsValid(string $retiredKey, object $retired, string $desiredKey, object $desired): bool
    {
        if ((int) $retired->id === (int) $desired->id) {
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

        if (XpScore::getObjectXp($retiredKey) !== XpScore::getObjectXp($desiredKey)) {
            $this->error('The two tags have different XP values.');

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

        $this->line("{$mode}: {$entry['retired_key']} ({$entry['retired_id']}) → {$entry['desired_key']} ({$entry['desired_id']})");
        $this->line("Rows: {$change['rows']}");
        $this->line("Tags: {$change['tags']}");
        $this->line('Example photo IDs: ' . ($change['example_photo_ids'] === []
            ? 'none'
            : implode(', ', $change['example_photo_ids'])));
    }

    /** @param array{retired_key:string, retired_id:int, desired_key:string, desired_id:int} $entry */
    private function apply(
        array $entry,
        GeneratePhotoSummaryService $summaries,
        MetricsService $metrics,
    ): bool {
        $retiredId = (int) $entry['retired_id'];
        $desiredId = (int) $entry['desired_id'];

        try {
            $pivots = $this->prepareRetirement($retiredId, $desiredId);

            Photo::withTrashed()
                ->whereHas('photoTags', fn ($query) => $query->where('litter_object_id', $retiredId))
                ->chunkById(200, function ($photos) use ($retiredId, $desiredId, $pivots, $summaries, $metrics): void {
                    if (!$this->redisIsReachable()) {
                        throw new \RuntimeException('Redis became unavailable.');
                    }

                    DB::transaction(function () use ($photos, $retiredId, $desiredId, $pivots, $summaries, $metrics): void {
                        $photoIds = $photos->pluck('id');

                        foreach ($pivots as $categoryId => $desiredCloId) {
                            DB::table('photo_tags')
                                ->whereIn('photo_id', $photoIds)
                                ->where('litter_object_id', $retiredId)
                                ->where('category_id', $categoryId)
                                ->update([
                                    'litter_object_id' => $desiredId,
                                    'category_litter_object_id' => $desiredCloId,
                                ]);
                        }

                        DB::table('photo_tags')
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
                            $summaries->run($photo);

                            if ($photo->processed_at !== null && $photo->deleted_at === null) {
                                $metrics->processPhoto($photo);
                            }
                        }
                    });
                });
        } catch (Throwable $e) {
            $this->error('Migration stopped: ' . $e->getMessage());

            return false;
        }

        return true;
    }

    /** @return array<int, int> category id => replacement CLO id */
    private function prepareRetirement(int $retiredId, int $desiredId): array
    {
        return DB::transaction(function () use ($retiredId, $desiredId): array {
            DB::table('litter_objects')
                ->where('id', $retiredId)
                ->update([
                    'retired_at' => now(),
                    'merged_into_id' => $desiredId,
                    'updated_at' => now(),
                ]);

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

                $desiredCloId = DB::table('category_litter_object')
                    ->where('category_id', $categoryId)
                    ->where('litter_object_id', $desiredId)
                    ->value('id');

                if ($desiredCloId === null) {
                    $desiredCloId = DB::table('category_litter_object')->insertGetId([
                        'category_id' => $categoryId,
                        'litter_object_id' => $desiredId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $pivots[$categoryId] = (int) $desiredCloId;

                if ($retiredCloId !== null) {
                    DB::table('user_quick_tags')
                        ->where('clo_id', $retiredCloId)
                        ->update(['clo_id' => $desiredCloId, 'updated_at' => now()]);
                }
            }

            return $pivots;
        });
    }
}
