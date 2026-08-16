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
        {--entry= : entry_id to operate on}
        {--apply : execute the migration (dry-run by default)}
        {--queue= : override the CSV file (relative to base_path)}';

    protected $description = 'Retire one litter object and move its data to another.';

    private const QUEUE = 'readme/audit/TagRetirements-2026-08.csv';

    public function handle(GeneratePhotoSummaryService $summaries, MetricsService $metrics): int
    {
        $entryId = (string) $this->option('entry');

        if ($entryId === '') {
            $this->error('--entry is required.');

            return self::FAILURE;
        }

        $entry = $this->findEntry($entryId);

        if ($entry === null) {
            $this->error("Unknown entry: {$entryId}");

            return self::FAILURE;
        }

        $change = $this->measure((int) $entry['retired_id']);
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
     * @param array<string, string> $entry
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

    /** @param array<string, string> $entry */
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

    /** @return array<string, string>|null */
    private function findEntry(string $entryId): ?array
    {
        $path = base_path($this->option('queue') ?: self::QUEUE);
        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);

        while (($values = fgetcsv($handle)) !== false) {
            if (count($values) !== count($header)) {
                continue;
            }

            $entry = array_combine($header, $values);

            if ($entry['entry_id'] === $entryId) {
                fclose($handle);

                return $entry;
            }
        }

        fclose($handle);

        return null;
    }
}
