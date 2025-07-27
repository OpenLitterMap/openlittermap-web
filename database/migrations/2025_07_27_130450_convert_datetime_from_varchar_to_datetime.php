<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Find invalid rows (NULL, empty, or calendar-invalid)
        $invalidRows = DB::select("
            SELECT id, `datetime`
            FROM photos
            WHERE `datetime` IS NULL
               OR `datetime` = ''
               OR STR_TO_DATE(`datetime`, '%Y-%m-%d %H:%i:%s') IS NULL
        ");
        $invalidCount = count($invalidRows);

        if ($invalidCount === 1 && (int)$invalidRows[0]->id === 945) {
            // 2) Auto-fix the known offender: 'YYYY-MM-DD 24:MM:SS' => (next day) '00:MM:SS'
            $this->comment("Auto-fixing known offender id=945 (normalizing hour 24 to next day 00).");
            DB::statement("
                UPDATE photos
                SET `datetime` = CONCAT(
                    DATE_ADD(SUBSTRING(`datetime`, 1, 10), INTERVAL 1 DAY),
                    ' ',
                    '00',
                    SUBSTRING(`datetime`, 14)
                )
                WHERE id = 945
                  AND `datetime` REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2} 24:[0-9]{2}:[0-9]{2}$'
            ");

            // re-check after fix
            $invalidRows = DB::select("
                SELECT id, `datetime`
                FROM photos
                WHERE `datetime` IS NULL
                   OR `datetime` = ''
                   OR STR_TO_DATE(`datetime`, '%Y-%m-%d %H:%i:%s') IS NULL
            ");
            $invalidCount = count($invalidRows);
        }

        if ($invalidCount > 0) {
            // 3) More than one invalid row: print them all and abort
            $this->comment("Found {$invalidCount} invalid datetime rows. Please fix them and rerun the migration.");
            foreach ($invalidRows as $r) {
                $val = is_null($r->datetime) ? 'NULL' : "'{$r->datetime}'";
                $this->comment("ID: {$r->id}, datetime: {$val}");
            }
            throw new RuntimeException('Aborting: invalid datetime rows detected.');
        }

        // 4) Drop legacy index if present (best-effort)
        $this->dropIndexIfExists('photos', 'photos_datetime_index');
        $this->dropIndexIfExists('photos', 'idx_photos_datetime');

        // 5) Convert to DATETIME NOT NULL in-place
        // MySQL will choose the best algorithm (INPLACE if possible)
        DB::statement("ALTER TABLE `photos` MODIFY COLUMN `datetime` DATETIME NOT NULL");

        // 6) Add a single composite index for ORDER BY datetime DESC, id ASC
        DB::statement("CREATE INDEX `photos_datetime_id_idx` ON `photos` (`datetime`, `id`)");
    }

    public function down(): void
    {
        // Drop new composite index
        $this->dropIndexIfExists('photos', 'photos_datetime_id_idx');

        // Convert back to VARCHAR
        DB::statement("ALTER TABLE `photos` MODIFY COLUMN `datetime` VARCHAR(255) NOT NULL");

        // Recreate old single-column index
        DB::statement("CREATE INDEX `photos_datetime_index` ON `photos` (`datetime`)");

        $this->comment('Datetime column reverted to VARCHAR(255).');
    }

    /**
     * Drop an index if it exists
     */
    private function dropIndexIfExists(string $table, string $index): void
    {
        try {
            DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$index}`");
        } catch (\Throwable $e) {
            // Index doesn't exist, that's fine
        }
    }

    /**
     * Output a comment during migration
     */
    private function comment(string $message): void
    {
        if (app()->runningInConsole()) {
            echo "\e[32m{$message}\e[0m\n";
        }
    }
};
