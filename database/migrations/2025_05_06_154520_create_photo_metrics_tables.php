<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Four-level time-series fact table.
 *
 *  • timescale: 1 = daily, 2 = weekly (ISO-8601), 3 = monthly, 4 = yearly
 *  • location : global / country / state / city
 *
 * Primary-key columns double as the partition key, so every row lives in the
 * correct partition and can be reached with a single index seek.
 *
 * Each CHECK constraint is conditional on `timescale`, letting us store the
 * canonical “bucket start” date for weekly / monthly / yearly rows while still
 * enforcing data integrity.
 */
return new class extends Migration
{
    public function up(): void
    {
        /** @lang SQL */
        DB::statement(<<<'SQL'
CREATE TABLE photo_metrics (
  /* ──────────── dimensions ──────────── */
  timescale     TINYINT  UNSIGNED NOT NULL,                                   -- 1,2,3,4
  location_type ENUM('global','country','state','city') NOT NULL,
  location_id   BIGINT   UNSIGNED NOT NULL,

  /* ──────────── bucket start date ───── */
  day       DATE            NOT NULL,                                         -- calendar date
  iso_week  TINYINT  UNSIGNED NOT NULL,                                       -- 1-53 or 0
  month     TINYINT  UNSIGNED NOT NULL,                                       -- 1-12 or 0
  year      SMALLINT UNSIGNED NOT NULL,                                       -- 4-digit, ISO for weekly rows

  /* ──────────── measures ────────────── */
  uploads  INT UNSIGNED NOT NULL DEFAULT 0,
  tags     INT UNSIGNED NOT NULL DEFAULT 0,
  brands   INT UNSIGNED NOT NULL DEFAULT 0,

  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,

  /* ──────────── primary / secondary keys ─────────── */
  PRIMARY KEY (timescale, location_type, location_id, year, month, iso_week, day),
  KEY idx_daily_loc_day (timescale, location_type, location_id, day),

  /* ──────────── data-quality guards ──────────────── */

  -- 0. timescale must be one of the four enumerated values
  CONSTRAINT chk_timescale_range CHECK (timescale IN (1,2,3,4)),

  -- 1. ISO-week number only matters for daily + weekly buckets
  CONSTRAINT chk_iso_week CHECK (
        (timescale IN (1,2) AND WEEK(day,3) = iso_week)
     OR (timescale IN (3,4) AND iso_week = 0)
  ),

  -- 2. Month column is zero for yearly buckets, exact otherwise
  CONSTRAINT chk_month CHECK (
        (timescale IN (1,2,3) AND MONTH(day) = month)
     OR (timescale = 4       AND month = 0)
  ),

  -- 3. Year column:
  --      • exact calendar year for daily / monthly / yearly
  --      • ISO-week year can differ by ±1 for weekly buckets
  CONSTRAINT chk_year CHECK (
        (timescale = 2  AND ABS(year - YEAR(day)) <= 1)
     OR (timescale <> 2 AND YEAR(day) = year)
  )
) ENGINE = InnoDB
  /* ──────────── partition so each scale lives on its own leaf ─────────── */
PARTITION BY LIST COLUMNS (timescale) (
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
