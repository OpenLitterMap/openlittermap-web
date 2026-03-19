<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Drop the chk_user_location constraint to allow per-user rows at all location scopes.
     * This enables time-filtered leaderboards at country/state/city level.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE metrics DROP CHECK chk_user_location');

        // Index for time-filtered leaderboard queries:
        // WHERE timescale=? AND location_type=? AND location_id=? AND user_id > 0 ORDER BY xp DESC
        DB::statement('CREATE INDEX idx_leaderboard ON metrics (timescale, location_type, location_id, year, month, xp DESC)');
    }

    /**
     * Restore the original constraint (per-user rows only at global scope).
     */
    public function down(): void
    {
        DB::statement('DROP INDEX idx_leaderboard ON metrics');
        DB::statement('ALTER TABLE metrics ADD CONSTRAINT chk_user_location CHECK (user_id = 0 OR (location_type = 0 AND location_id = 0))');
    }
};
