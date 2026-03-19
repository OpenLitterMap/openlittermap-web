<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Replace idx_leaderboard with a version that includes bucket_date.
     * Daily queries (timescale=1) filter by bucket_date — without it in the index,
     * MySQL scans all daily rows for the month before filtering.
     */
    public function up(): void
    {
        DB::statement('DROP INDEX idx_leaderboard ON metrics');
        DB::statement('CREATE INDEX idx_leaderboard ON metrics (timescale, location_type, location_id, year, month, bucket_date, xp DESC)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX idx_leaderboard ON metrics');
        DB::statement('CREATE INDEX idx_leaderboard ON metrics (timescale, location_type, location_id, year, month, xp DESC)');
    }
};
