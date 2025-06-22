<?php

namespace App\Console\Commands\Clusters;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillTiles extends Command
{
    protected $signature = 'clustering:backfill
                            {--batch=20000  : DISTINCT tile keys per chunk}
                            {--sleep=0      : Seconds to pause between chunks}
                            {--dry-run      : Only show what would execute}';

    protected $description = 'Populate dirty_tiles with every (tile_key) present in verified photos';

    /* --------------------------------------------------------------------- */
    public function handle(): int
    {
        $batch     = (int) $this->option('batch');
        $sleep     = (int) $this->option('sleep');
        $dryRun    =     $this->option('dry-run');

        /* 1. count distinct tile keys up-front (fast with idx_tile_key)  */
        $totalKeys = DB::table('photos')
            ->whereNotNull('tile_key')
            ->where('verified', 2)
            ->distinct()
            ->count('tile_key');

        if ($totalKeys === 0) {
            $this->warn('No verified photos with a tile_key found');
            return 0;
        }

        $this->info("Back-filling dirty_tiles for {$totalKeys} distinct tiles");
        if ($dryRun) {
            $this->line('<fg=yellow>[DRY-RUN]</> No data will be written.');
            return 0;
        }

        $bar        = $this->output->createProgressBar($totalKeys);
        $processed  = 0;
        $upserts    = 0;
        $now        = CarbonImmutable::now()->toDateTimeString();

        /* 2. stream DISTINCT tile keys in manageable chunks ------------- */
        DB::table('photos')
            ->selectRaw('DISTINCT tile_key')
            ->whereNotNull('tile_key')
            ->where('verified', 2)
            ->orderBy('tile_key')
            ->chunk($batch, function ($chunk) use (&$processed, &$upserts, $bar, $now, $sleep) {

                /* map into rows for upsert */
                $rows = $chunk->map(fn ($row) => [
                    'tile_key'  => $row->tile_key,
                    'seen_at'   => $now,
                    'processed' => 0,
                ])->all();

                /* upsert keeps earliest row, refreshes seen_at */
                DB::table('dirty_tiles')
                    ->upsert($rows, ['tile_key'], ['seen_at', 'processed']);

                $upserts   += count($rows);
                $processed += count($rows);
                $bar->advance(count($rows));

                if ($sleep > 0) {
                    sleep($sleep);
                }
            });

        $bar->finish();
        $this->newLine(2);

        /* 3. summary ---------------------------------------------------- */
        $this->table(
            ['Metric',                'Value'],
            [
                ['Distinct tiles processed', number_format($processed)],
                ['Upsert statements run',    number_format($upserts)],
                ['Dirty tiles queued',       DB::table('dirty_tiles')->where('processed',0)->count()],
            ]
        );

        $this->info("\nRun <comment>php artisan clusters:refresh</comment> to build clusters.");

        return 0;
    }

    /* ----------------------------------------------------------------- */
}
