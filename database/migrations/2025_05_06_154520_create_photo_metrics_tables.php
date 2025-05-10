<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        /* 1=daily 2=weekly 3=monthly 4=yearly */
        DB::statement(<<<'SQL'
CREATE TABLE photo_metrics (
  timescale     TINYINT UNSIGNED NOT NULL
                CHECK (timescale IN (1,2,3,4)),
  location_type ENUM('global','country','state','city') NOT NULL,
  location_id   BIGINT UNSIGNED NOT NULL,

  day       DATE,
  iso_week  TINYINT UNSIGNED,
  month     TINYINT UNSIGNED,
  year      SMALLINT UNSIGNED NOT NULL,

  uploads     INT UNSIGNED NOT NULL DEFAULT 0,
  tags        INT UNSIGNED NOT NULL DEFAULT 0,
  brands      INT UNSIGNED NOT NULL DEFAULT 0,

  created_at  TIMESTAMP NULL,
  updated_at  TIMESTAMP NULL,

  PRIMARY KEY (
      timescale, location_type, location_id,
      year, month, iso_week, day
  ),

  KEY idx_daily_loc_day (timescale, location_type, location_id, day)
) ENGINE=InnoDB
PARTITION BY LIST COLUMNS(timescale) (
  PARTITION p_daily   VALUES IN (1),
  PARTITION p_weekly  VALUES IN (2),
  PARTITION p_monthly VALUES IN (3),
  PARTITION p_yearly  VALUES IN (4)
);
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS photo_metrics');
    }
};
