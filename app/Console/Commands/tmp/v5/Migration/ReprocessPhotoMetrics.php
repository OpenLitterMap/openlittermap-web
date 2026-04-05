<?php

namespace App\Console\Commands\tmp\v5\Migration;

use App\Models\Photo;
use App\Services\Metrics\MetricsService;
use Illuminate\Console\Command;

class ReprocessPhotoMetrics extends Command
{
    protected $signature = 'olm:reprocess-metrics
        {--from-file= : File with one photo ID per line}
        {--photo-ids= : Comma-separated photo IDs}
        {--batch=100 : Batch size}
        {--log= : Write output to log file}
        {--dry-run : Show what would change without processing}';

    protected $description = 'Reprocess MetricsService for specific photos (delta-based update)';

    private int $processed = 0;
    private int $changed = 0;
    private int $skipped = 0;
    private int $errors = 0;

    /** @var resource|null */
    private $logFile = null;

    public function handle(MetricsService $metricsService): int
    {
        $this->openLog();

        $photoIds = $this->resolvePhotoIds();

        if ($photoIds === null || $photoIds->isEmpty()) {
            $this->log('ERROR: Specify --photo-ids or --from-file', 'error');

            return 1;
        }

        $batchSize = (int) $this->option('batch');
        $dryRun = $this->option('dry-run');
        $mode = $dryRun ? 'DRY-RUN' : 'LIVE';

        $this->log("=== Reprocess Photo Metrics ({$mode}) ===");
        $this->log("Photos: {$photoIds->count()}");
        $this->log('');

        $photoIds->chunk($batchSize)->each(function ($chunk) use ($metricsService, $dryRun) {
            $photos = Photo::withTrashed()->whereIn('id', $chunk)->get();

            foreach ($photos as $photo) {
                $this->processPhoto($photo, $metricsService, $dryRun);
            }

            $this->log("  Progress: {$this->processed} processed, {$this->changed} changed, {$this->skipped} skipped, {$this->errors} errors");
        });

        $this->log('');
        $this->log('=== SUMMARY ===');
        $this->log("Processed: {$this->processed}");
        $this->log("Changed (delta applied): {$this->changed}");
        $this->log("Skipped (no delta): {$this->skipped}");
        $this->log("Errors: {$this->errors}");

        $this->closeLog();

        return 0;
    }

    private function processPhoto(Photo $photo, MetricsService $metricsService, bool $dryRun): void
    {
        try {
            $oldProcessedXp = $photo->processed_xp;
            $oldProcessedFp = $photo->processed_fp;
            $currentXp = $photo->xp;

            if ($dryRun) {
                $this->processed++;
                $xpDelta = $currentXp - ($oldProcessedXp ?? 0);
                $this->log("    Photo {$photo->id}: xp delta would be {$xpDelta} ({$oldProcessedXp}→{$currentXp})");
                $this->changed++;

                return;
            }

            $metricsService->processPhoto($photo);
            $photo->refresh();

            $this->processed++;

            if ($photo->processed_xp !== $oldProcessedXp || $photo->processed_fp !== $oldProcessedFp) {
                $this->changed++;
                $this->log("    Photo {$photo->id}: metrics updated, processed_xp: {$oldProcessedXp}→{$photo->processed_xp}");
            } else {
                $this->skipped++;
            }
        } catch (\Throwable $e) {
            $this->errors++;
            $this->log("    Photo {$photo->id}: ERROR — {$e->getMessage()}", 'error');
        }
    }

    private function resolvePhotoIds()
    {
        if ($ids = $this->option('photo-ids')) {
            return collect(explode(',', $ids))->map(fn ($id) => (int) trim($id))->filter();
        }

        if ($file = $this->option('from-file')) {
            if (! file_exists($file)) {
                $this->log("File not found: {$file}", 'error');

                return null;
            }

            return collect(file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))
                ->map(fn ($id) => (int) trim($id))
                ->filter();
        }

        return null;
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
