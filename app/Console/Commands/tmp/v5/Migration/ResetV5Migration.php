<?php

declare(strict_types=1);

namespace App\Console\Commands\tmp\v5\Migration;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;

class ResetV5Migration extends Command
{
    /** php artisan olm:v5:reset --force */
    protected $signature   = 'olm:v5:reset {--force : skip confirmation prompt}';
    protected $description = '⚠️  Reset all v5 migration changes (photos columns, tags, metrics, achievements, Redis)';

    public function handle(): int
    {
        if (!$this->option('force') && !$this->confirm(
                'This will RESET all v5 migration changes: ' .
                'photos.summary/xp/migrated_at/processed_*, photo_tags, metrics, achievements, Redis. ' .
                'Continue?'
            )) {
            $this->info('Aborted.');
            return self::SUCCESS;
        }

        $this->info('Starting v5 migration reset...');

        /* ------------------------------------------------------------------ */
        /* 1️⃣  Reset all migration-modified columns on photos table          */
        /* ------------------------------------------------------------------ */
        $this->info('Resetting photos table columns...');

        $columnsToReset = [
            'summary' => null,
            'xp' => 0,
            'migrated_at' => null,
            'processed_at' => null,
            'processed_fp' => null,
            'processed_tags' => null,
            'processed_xp' => null
        ];

        $updated = DB::table('photos')->update($columnsToReset);
        $this->line("✓ Reset {$updated} photo records");

        /* ------------------------------------------------------------------ */
        /* 2️⃣  Truncate tables populated during migration                    */
        /* ------------------------------------------------------------------ */
        $this->info('Truncating migration-generated tables...');

        $tablesToTruncate = [
            'photo_tags',
            'photo_tag_extra_tags',
            'metrics',              // Time-series metrics data
            'user_achievements',    // Achievement unlocks
        ];

        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($tablesToTruncate as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
                $this->line("✓ Truncated {$table}");
            }
        }
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        /* ------------------------------------------------------------------ */
        /* 3️⃣  Clear all Redis metrics and rankings                          */
        /* ------------------------------------------------------------------ */
        $this->info('Clearing Redis metrics...');

        // Get all user IDs that might have Redis data
        $userIds = DB::table('users')->pluck('id');

        $patterns = [];
        foreach ($userIds as $userId) {
            $patterns[] = "user:{$userId}:*";
            $patterns[] = "metrics:user:{$userId}:*";
            $patterns[] = "streaks:user:{$userId}:*";
        }

        // Add global patterns
        $patterns = array_merge($patterns, [
            'global:*',
            'country:*:*',
            'state:*:*',
            'city:*:*',
            'leaderboard:*',
            'rankings:*',
            'metrics:*',
            'streaks:*'
        ]);

        // Delete matching keys
        $totalDeleted = 0;
        foreach ($patterns as $pattern) {
            $keys = Redis::keys($pattern);
            if (!empty($keys)) {
                $deleted = Redis::del($keys);
                $totalDeleted += $deleted;
            }
        }

        $this->line("✓ Deleted {$totalDeleted} Redis keys");

        /* ------------------------------------------------------------------ */
        /* 4️⃣  Clear Laravel caches used by migration                        */
        /* ------------------------------------------------------------------ */
        $this->info('Clearing migration caches...');

        // Clear specific cache tags if they exist
        try {
            cache()->tags(['achievements'])->flush();
            $this->line('✓ Cleared achievements cache');
        } catch (\Exception $e) {
            // Tag might not exist
        }

        try {
            cache()->tags(['tag_key_cache'])->flush();
            $this->line('✓ Cleared tag key cache');
        } catch (\Exception $e) {
            // Tag might not exist
        }

        // Clear any cached user metrics
        $cacheKeys = [];
        foreach ($userIds as $userId) {
            $cacheKeys[] = "achievements:unlocked:{$userId}";
            $cacheKeys[] = "user:metrics:{$userId}";
        }

        foreach ($cacheKeys as $key) {
            cache()->forget($key);
        }
        $this->line('✓ Cleared user-specific caches');

        /* ------------------------------------------------------------------ */
        /* 5️⃣  Summary                                                        */
        /* ------------------------------------------------------------------ */
        $this->newLine();
        $this->info('🔄 Reset Summary:');
        $this->info('═════════════════');
        $this->table(
            ['Component', 'Status'],
            [
                ['Photos table', "✓ Reset {$updated} records"],
                ['Photo tags', '✓ Truncated'],
                ['Metrics table', '✓ Truncated'],
                ['User achievements', '✓ Truncated'],
                ['Redis metrics', "✓ Deleted {$totalDeleted} keys"],
                ['Cache', '✓ Cleared'],
            ]
        );

        $this->newLine();
        $this->info('✅ V5 migration has been completely reset.');
        $this->info('You can now run "php artisan olm:v5" to start fresh.');

        return self::SUCCESS;
    }
}
