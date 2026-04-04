<?php

namespace App\Console\Commands\tmp\v5\Migration;

use App\Models\Photo;
use App\Services\Tags\GeneratePhotoSummaryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RegenerateSummaries extends Command
{
    protected $signature = 'olm:regenerate-summaries
        {--photo-ids= : Comma-separated photo IDs to regenerate}
        {--from-file= : File with one photo ID per line}
        {--orphan-fix : Regenerate migrated photos with stale summaries (resumable)}
        {--limit=0 : Limit number of photos to process (0 = all)}
        {--batch=500 : Batch size for DB chunking and processing}
        {--log= : Write output to log file}
        {--dry-run : Show what would change without saving}';

    protected $description = 'Regenerate photo summaries from photo_tags (source of truth). No metrics/events/Redis side effects.';

    private int $processed = 0;
    private int $changed = 0;
    private int $unchanged = 0;
    private int $skipped = 0;
    private int $errors = 0;
    private int $limit = 0;

    /** @var resource|null */
    private $logFile = null;

    public function handle(GeneratePhotoSummaryService $summaryService): int
    {
        $this->openLog();

        $this->limit = (int) $this->option('limit');
        $batchSize = (int) $this->option('batch');
        $dryRun = $this->option('dry-run');
        $mode = $dryRun ? 'DRY-RUN' : 'LIVE';

        $this->log("=== Regenerate Summaries ({$mode}) ===");

        // --photo-ids and --from-file: small sets, load into memory
        if ($ids = $this->option('photo-ids')) {
            $photoIds = collect(explode(',', $ids))->map(fn ($id) => (int) trim($id))->filter();
            $this->log("Source: --photo-ids ({$photoIds->count()} photos)");

            return $this->processCollection($photoIds, $batchSize, $summaryService, $dryRun);
        }

        if ($file = $this->option('from-file')) {
            if (! file_exists($file)) {
                $this->log("File not found: {$file}", 'error');

                return 1;
            }
            $photoIds = collect(file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))
                ->map(fn ($id) => (int) trim($id))
                ->filter();
            $this->log("Source: --from-file ({$photoIds->count()} photos)");

            return $this->processCollection($photoIds, $batchSize, $summaryService, $dryRun);
        }

        if ($this->option('orphan-fix')) {
            return $this->processOrphanFix($batchSize, $summaryService, $dryRun);
        }

        $this->log('ERROR: Specify --photo-ids, --from-file, or --orphan-fix', 'error');

        return 1;
    }

    /**
     * Process a known collection of photo IDs (small sets from --photo-ids or --from-file).
     */
    private function processCollection($photoIds, int $batchSize, GeneratePhotoSummaryService $summaryService, bool $dryRun): int
    {
        $photoIds = $this->limit > 0 ? $photoIds->take($this->limit) : $photoIds;
        $this->log("Photos to process: {$photoIds->count()}");
        $this->log('');

        $photoIds->chunk($batchSize)->each(function ($chunk) use ($summaryService, $dryRun) {
            $photos = Photo::withTrashed()->whereIn('id', $chunk)->get();

            foreach ($photos as $photo) {
                if ($this->limitReached()) {
                    return false;
                }
                $this->processPhoto($photo, $summaryService, $dryRun);
            }

            $this->logProgress();
        });

        return $this->finish();
    }

    /**
     * Chunked, resumable processing for orphan-fix affected photos.
     * Uses DB-level chunking (never loads all IDs into memory).
     * Resumable: skips photos whose summary already has clo_id set on first tag.
     */
    private function processOrphanFix(int $batchSize, GeneratePhotoSummaryService $summaryService, bool $dryRun): int
    {
        $total = DB::table('photos')
            ->whereNotNull('migrated_at')
            ->whereNotNull('summary')
            ->count();

        $this->log("Source: --orphan-fix (scanning {$total} migrated photos, skipping already-regenerated)");
        $this->log('');

        $stopped = false;

        DB::table('photos')
            ->select('id', 'summary')
            ->whereNotNull('migrated_at')
            ->whereNotNull('summary')
            ->orderBy('id')
            ->chunkById($batchSize, function ($rows) use ($summaryService, $dryRun, &$stopped) {
                if ($stopped) {
                    return false;
                }

                // Filter to photos with stale summaries (clo_id is null on first tag)
                $staleIds = [];
                foreach ($rows as $row) {
                    if ($this->hasStaleSummary($row->summary)) {
                        $staleIds[] = $row->id;
                    } else {
                        $this->skipped++;
                    }
                }

                if (empty($staleIds)) {
                    return true;
                }

                $photos = Photo::withTrashed()->whereIn('id', $staleIds)->get();

                foreach ($photos as $photo) {
                    if ($this->limitReached()) {
                        $stopped = true;

                        return false;
                    }
                    $this->processPhoto($photo, $summaryService, $dryRun);
                }

                $this->logProgress();
            });

        return $this->finish();
    }

    /**
     * Check if a photo's summary has a stale first tag (clo_id is null).
     * Already-regenerated photos will have clo_id set.
     */
    private function hasStaleSummary(string $summaryJson): bool
    {
        $summary = json_decode($summaryJson, true);

        if (! $summary || empty($summary['tags'])) {
            return false;
        }

        // If any tag has a null clo_id, the summary is stale
        foreach ($summary['tags'] as $tag) {
            if (! isset($tag['clo_id']) || $tag['clo_id'] === null) {
                // Extra-tag-only entries (no object) legitimately have null clo_id.
                // Only stale if the tag has an object_id (orphan fix changed these).
                if (isset($tag['object_id']) && $tag['object_id'] > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function processPhoto(Photo $photo, GeneratePhotoSummaryService $summaryService, bool $dryRun): void
    {
        try {
            $oldSummary = $photo->summary;
            $oldXp = $photo->xp;

            if ($dryRun) {
                $tagCount = $photo->photoTags()->count();
                $this->processed++;
                $this->log("    Photo {$photo->id}: {$tagCount} tags, would regenerate");
                $this->changed++;

                return;
            }

            // Pure summary write — no events, no observers, no MetricsService
            Photo::withoutEvents(function () use ($photo, $summaryService) {
                $summaryService->run($photo);
            });

            $photo->refresh();
            $this->processed++;

            $summaryChanged = json_encode($oldSummary) !== json_encode($photo->summary);
            $xpChanged = $oldXp !== $photo->xp;

            if ($summaryChanged || $xpChanged) {
                $this->changed++;
                $xpDelta = $photo->xp - $oldXp;
                if ($xpDelta !== 0) {
                    $this->log("    Photo {$photo->id}: summary updated, xp: {$oldXp}→{$photo->xp}");
                }
            } else {
                $this->unchanged++;
            }
        } catch (\Throwable $e) {
            $this->errors++;
            $this->log("    Photo {$photo->id}: ERROR — {$e->getMessage()}", 'error');
        }
    }

    private function limitReached(): bool
    {
        return $this->limit > 0 && $this->processed >= $this->limit;
    }

    private function logProgress(): void
    {
        $total = $this->processed + $this->skipped;
        $msg = "  [{$total}] {$this->processed} processed ({$this->changed} changed, {$this->unchanged} unchanged), {$this->skipped} skipped, {$this->errors} errors";

        // Overwrite line on terminal for compact progress
        $this->output->write("\r\033[K" . $msg);
        $this->newLine();

        if ($this->logFile) {
            fwrite($this->logFile, '[' . now()->toDateTimeString() . "] {$msg}\n");
        }
    }

    private function finish(): int
    {
        $this->log('');
        $this->log('=== SUMMARY ===');
        $this->log("Processed: {$this->processed}");
        $this->log("Changed: {$this->changed}");
        $this->log("Unchanged: {$this->unchanged}");
        $this->log("Skipped (already regenerated): {$this->skipped}");
        $this->log("Errors: {$this->errors}");

        $this->closeLog();

        return 0;
    }

    private function log(string $message, string $level = 'info'): void
    {
        match ($level) {
            'warn' => $this->warn($message),
            'error' => $this->error($message),
            default => $message === '' ? $this->newLine() : $this->info($message),
        };

        if ($this->logFile) {
            fwrite($this->logFile, '[' . now()->toDateTimeString() . "] {$message}\n");
        }
    }

    private function openLog(): void
    {
        $path = $this->option('log');

        if (! $path) {
            return;
        }

        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->logFile = fopen($path, 'a');

        if ($this->logFile) {
            $this->info("Logging to: {$path}");
            fwrite($this->logFile, "\n=== " . now()->toDateTimeString() . " ===\n");
        }
    }

    private function closeLog(): void
    {
        if ($this->logFile) {
            fclose($this->logFile);
            $this->logFile = null;
        }
    }
}
