<?php

declare(strict_types=1);

namespace App\Console\Commands\tmp\v5\PostMigAug2026;

use App\Models\Photo;
use App\Services\Metrics\MetricsService;
use App\Services\Tags\GeneratePhotoSummaryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

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
        $retiredId = DB::table('litter_objects')->where('key', $retiredKey)->value('id');
        $desiredId = DB::table('litter_objects')->where('key', $desiredKey)->value('id');

        if ($retiredId === null) {
            $this->error("Unknown litter object: {$retiredKey}");

            return self::FAILURE;
        }

        if ($desiredId === null) {
            $this->error("Unknown litter object: {$desiredKey}");

            return self::FAILURE;
        }

        $entry = [
            'retired_key' => $retiredKey,
            'retired_id' => (int) $retiredId,
            'desired_key' => $desiredKey,
            'desired_id' => (int) $desiredId,
        ];
        $change = $this->measure((int) $retiredId);
        $this->report($entry, $change, !$this->option('apply'));

        if (!$this->option('apply')) {
            return self::SUCCESS;
        }

        $this->apply($entry, $change['photo_ids'], $summaries, $metrics);

        return self::SUCCESS;
    }

    /** @return array{rows:int, tags:int, photo_ids:array<int, int>} */
    private function measure(int $retiredId): array
    {
        $query = DB::table('photo_tags')->where('litter_object_id', $retiredId);

        return [
            'rows' => (clone $query)->count(),
            'tags' => (int) (clone $query)->sum('quantity'),
            'photo_ids' => (clone $query)
                ->distinct()
                ->orderBy('photo_id')
                ->pluck('photo_id')
                ->map('intval')
                ->all(),
        ];
    }

    /**
     * @param array{retired_key:string, retired_id:int, desired_key:string, desired_id:int} $entry
     * @param array{rows:int, tags:int, photo_ids:array<int, int>} $change
     */
    private function report(array $entry, array $change, bool $dryRun): void
    {
        $mode = $dryRun ? 'DRY RUN' : 'APPLY';
        $sample = array_slice($change['photo_ids'], 0, 5);

        $this->line("{$mode}: {$entry['retired_key']} ({$entry['retired_id']}) → {$entry['desired_key']} ({$entry['desired_id']})");
        $this->line("Rows: {$change['rows']}");
        $this->line("Tags: {$change['tags']}");
        $this->line('Example photo IDs: ' . ($sample === [] ? 'none' : implode(', ', $sample)));
    }

    /** @param array{retired_key:string, retired_id:int, desired_key:string, desired_id:int} $entry */
    private function apply(
        array $entry,
        array $photoIds,
        GeneratePhotoSummaryService $summaries,
        MetricsService $metrics
    ): void {
        $retiredId = (int) $entry['retired_id'];
        $desiredId = (int) $entry['desired_id'];

        DB::transaction(function () use ($retiredId, $desiredId): void {
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

                DB::table('photo_tags')
                    ->where('litter_object_id', $retiredId)
                    ->where('category_id', $categoryId)
                    ->update([
                        'litter_object_id' => $desiredId,
                        'category_litter_object_id' => $desiredCloId,
                    ]);

                if ($retiredCloId !== null) {
                    DB::table('user_quick_tags')
                        ->where('clo_id', $retiredCloId)
                        ->update(['clo_id' => $desiredCloId, 'updated_at' => now()]);
                }
            }

            DB::table('photo_tags')
                ->where('litter_object_id', $retiredId)
                ->whereNull('category_id')
                ->update(['litter_object_id' => $desiredId]);
        });

        foreach (array_chunk($photoIds, 200) as $chunk) {
            foreach (Photo::withTrashed()->whereIn('id', $chunk)->get() as $photo) {
                $summaries->run($photo);

                if ($photo->processed_at !== null && $photo->deleted_at === null) {
                    $metrics->processPhoto($photo);
                }
            }
        }
    }

}
