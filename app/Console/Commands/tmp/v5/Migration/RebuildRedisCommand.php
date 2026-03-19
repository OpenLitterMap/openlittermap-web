<?php

declare(strict_types=1);

namespace App\Console\Commands\tmp\v5\Migration;

use App\Models\Photo;
use App\Services\Redis\RedisMetricsCollector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB, Redis};

class RebuildRedisCommand extends Command
{
    protected $signature = 'olm:redis:rebuild
        {--no-flush : Skip the Redis FLUSHDB step}
        {--batch=1000 : Photos per batch}';

    protected $description = 'Rebuild Redis from processed photo data (MySQL is source of truth)';

    public function handle(): int
    {
        $total = DB::table('photos')
            ->whereNotNull('processed_at')
            ->whereNull('deleted_at')
            ->count();

        if ($total === 0) {
            $this->error('No processed photos found. Run olm:v5 first.');
            return self::FAILURE;
        }

        $this->info("Found {$total} processed photos to replay into Redis.");

        if (!$this->option('no-flush')) {
            if (!$this->confirm('This will FLUSHDB Redis. Continue?')) {
                return self::FAILURE;
            }

            Redis::command('FLUSHDB');
            $this->info('Redis flushed.');
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $batch = (int) $this->option('batch');
        $replayed = 0;
        $skipped = 0;

        // Use chunked raw query for memory efficiency — only load what we need
        DB::table('photos')
            ->whereNotNull('processed_at')
            ->whereNull('deleted_at')
            ->select([
                'id', 'user_id', 'country_id', 'state_id', 'city_id',
                'processed_tags', 'processed_xp', 'created_at',
            ])
            ->orderBy('id')
            ->chunk($batch, function ($photos) use (&$replayed, &$skipped, $bar) {
                foreach ($photos as $row) {
                    $tags = json_decode($row->processed_tags ?? '{}', true);
                    $xp = (int) ($row->processed_xp ?? 0);

                    if ($xp === 0 && empty($tags)) {
                        $skipped++;
                        $bar->advance();
                        continue;
                    }

                    $litter = array_sum($tags['objects'] ?? []);

                    // Build a minimal Photo model for RedisMetricsCollector
                    // (it only reads user_id, country_id, state_id, city_id, created_at)
                    $photo = new Photo();
                    $photo->id = $row->id;
                    $photo->user_id = $row->user_id;
                    $photo->country_id = $row->country_id;
                    $photo->state_id = $row->state_id;
                    $photo->city_id = $row->city_id;
                    $photo->created_at = $row->created_at;
                    $photo->exists = true;

                    RedisMetricsCollector::processPhoto($photo, [
                        'tags' => $tags,
                        'litter' => $litter,
                        'xp' => $xp,
                    ], 'create');

                    $replayed++;
                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine(2);

        $this->info("Replayed: {$replayed}");
        if ($skipped > 0) {
            $this->warn("Skipped (no tags/xp): {$skipped}");
        }

        $this->newLine();
        $this->info('Verifying...');
        $this->verify();

        return self::SUCCESS;
    }

    private function verify(): void
    {
        // Compare Redis totals against MySQL metrics table
        $mysqlStats = DB::table('metrics')
            ->where('timescale', 0)
            ->where('location_type', 0) // global
            ->where('location_id', 0)
            ->where('user_id', 0) // aggregate row
            ->first(['uploads', 'tags', 'litter', 'xp']);

        $redisStats = Redis::hGetAll('{g}:stats');

        $this->table(
            ['Metric', 'MySQL (metrics table)', 'Redis'],
            [
                ['Uploads', $mysqlStats->uploads ?? 'N/A', $redisStats['photos'] ?? '0'],
                ['Litter', $mysqlStats->litter ?? 'N/A', $redisStats['litter'] ?? '0'],
                ['XP', $mysqlStats->xp ?? 'N/A', $redisStats['xp'] ?? '0'],
            ]
        );

        $leaderboardSize = Redis::zCard('{g}:lb:xp');
        $this->info("Global leaderboard entries: {$leaderboardSize}");
    }
}
