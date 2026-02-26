<?php

namespace App\Console\Commands\Clusters;

use App\Services\Clustering\ClusteringService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessDirtyTiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clustering:process-dirty
        {--limit=100 : Maximum number of tiles to process}
        {--team-limit=20 : Maximum number of teams to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process tiles and teams marked as dirty';

    /**
     * Execute the console command.
     */
    public function handle(ClusteringService $service): int
    {
        $hadFailures = false;

        $hadFailures = $this->processDirtyTiles($service) || $hadFailures;
        $hadFailures = $this->processDirtyTeams($service) || $hadFailures;

        return $hadFailures ? 1 : 0;
    }

    private function processDirtyTiles(ClusteringService $service): bool
    {
        $limit = (int) $this->option('limit');

        $tiles = DB::table('dirty_tiles')
            ->orderBy('changed_at')
            ->limit($limit)
            ->pluck('tile_key');

        if ($tiles->isEmpty()) {
            $this->info('No dirty tiles to process.');
            return false;
        }

        $this->info("Processing {$tiles->count()} dirty tiles...");
        $bar = $this->output->createProgressBar($tiles->count());

        $processed = 0;
        $failed = 0;

        foreach ($tiles as $tileKey) {
            try {
                $service->clusterTile($tileKey);

                DB::table('dirty_tiles')
                    ->where('tile_key', $tileKey)
                    ->delete();

                $processed++;
            } catch (\Exception $e) {
                $failed++;
                $this->error("\nFailed to process tile $tileKey: " . $e->getMessage());

                $service->markTileDirty($tileKey, true);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Tiles — Processed: $processed");
        if ($failed > 0) {
            $this->warn("Tiles — Failed: $failed (will retry with backoff)");
        }

        $remaining = DB::table('dirty_tiles')->count();
        if ($remaining > 0) {
            $this->info("Remaining dirty tiles: $remaining");
        }

        $ttl = config('clustering.dirty_tile_ttl', 24);
        DB::table('dirty_tiles')
            ->where('attempts', '>=', 3)
            ->where('changed_at', '<', now()->subHours($ttl))
            ->delete();

        return $failed > 0;
    }

    private function processDirtyTeams(ClusteringService $service): bool
    {
        $limit = (int) $this->option('team-limit');

        $teams = DB::table('dirty_teams')
            ->orderBy('changed_at')
            ->limit($limit)
            ->pluck('team_id');

        if ($teams->isEmpty()) {
            $this->info('No dirty teams to process.');
            return false;
        }

        $this->info("Processing {$teams->count()} dirty teams...");

        $processed = 0;
        $failed = 0;

        foreach ($teams as $teamId) {
            try {
                $service->clusterTeam($teamId);

                DB::table('dirty_teams')
                    ->where('team_id', $teamId)
                    ->delete();

                $processed++;
            } catch (\Exception $e) {
                $failed++;
                $this->error("Failed to process team $teamId: " . $e->getMessage());

                $service->markTeamDirty($teamId, true);
            }
        }

        $this->info("Teams — Processed: $processed");
        if ($failed > 0) {
            $this->warn("Teams — Failed: $failed (will retry with backoff)");
        }

        $remaining = DB::table('dirty_teams')->count();
        if ($remaining > 0) {
            $this->info("Remaining dirty teams: $remaining");
        }

        $ttl = config('clustering.dirty_tile_ttl', 24);
        DB::table('dirty_teams')
            ->where('attempts', '>=', 3)
            ->where('changed_at', '<', now()->subHours($ttl))
            ->delete();

        return $failed > 0;
    }
}
