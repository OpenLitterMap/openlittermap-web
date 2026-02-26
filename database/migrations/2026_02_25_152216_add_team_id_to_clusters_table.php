<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add team_id column (NOT NULL DEFAULT 0 so it can be in PRIMARY KEY)
        // 0 = global clusters, N = team-specific clusters
        if (!Schema::hasColumn('clusters', 'team_id')) {
            DB::statement('ALTER TABLE clusters ADD COLUMN team_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER tile_key');
        }

        // Drop existing primary/unique key and recreate with team_id
        if ($this->indexExists('clusters', 'PRIMARY')) {
            DB::statement('ALTER TABLE clusters DROP PRIMARY KEY');
        }
        if ($this->indexExists('clusters', 'uk_cluster')) {
            DB::statement('ALTER TABLE clusters DROP KEY uk_cluster');
        }

        // Recreate as primary key with team_id
        DB::statement('ALTER TABLE clusters ADD PRIMARY KEY (team_id, tile_key, zoom, year, cell_x, cell_y)');

        // Index for team queries
        if (!$this->indexExists('clusters', 'idx_team_zoom')) {
            DB::statement('CREATE INDEX idx_team_zoom ON clusters(team_id, zoom)');
        }
    }

    public function down(): void
    {
        // Drop team index
        if ($this->indexExists('clusters', 'idx_team_zoom')) {
            DB::statement('DROP INDEX idx_team_zoom ON clusters');
        }

        // Drop current primary key
        if ($this->indexExists('clusters', 'PRIMARY')) {
            DB::statement('ALTER TABLE clusters DROP PRIMARY KEY');
        }

        // Restore original primary key without team_id
        DB::statement('ALTER TABLE clusters ADD PRIMARY KEY (tile_key, zoom, year, cell_x, cell_y)');

        // Drop team_id column
        if (Schema::hasColumn('clusters', 'team_id')) {
            DB::statement('ALTER TABLE clusters DROP COLUMN team_id');
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $result = DB::selectOne("
            SELECT COUNT(*) as count
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
                AND table_name = ?
                AND index_name = ?
        ", [$table, $index]);

        return $result && $result->count > 0;
    }
};
