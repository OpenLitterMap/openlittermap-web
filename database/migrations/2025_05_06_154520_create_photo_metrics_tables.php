<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Time-series metrics fact table for OpenLitterMap v5
 *
 * Invariants:
 * - All timestamps in UTC
 * - Weekly rows use ISO week (Monday start), year = ISO week-year
 * - Monthly/yearly rows use calendar dates, bucket_date = first of period
 * - All-time rows use bucket_date = '1970-01-01'
 * - Tags count = objects + materials + brands (NOT categories to avoid double-counting)
 * - Uploads delta: create +1, update 0, delete -1
 * - All metrics are additive and support negative deltas
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE metrics (
    /* ──────────── Dimensions ──────────── */
    timescale     TINYINT UNSIGNED NOT NULL,      -- 0=all-time, 1=daily, 2=weekly, 3=monthly, 4=yearly
    location_type TINYINT UNSIGNED NOT NULL,      -- 0=global, 1=country, 2=state, 3=city
    location_id   BIGINT UNSIGNED NOT NULL,       -- 0 for global, otherwise location ID
    user_id       BIGINT UNSIGNED NOT NULL DEFAULT 0,  -- 0 for location metrics, >0 for user metrics

    /* ──────────── Time Bucket ──────────── */
    bucket_date   DATE NOT NULL,                  -- Bucket start date
    year          SMALLINT UNSIGNED NOT NULL,     -- ISO year for weekly, calendar year otherwise
    month         TINYINT UNSIGNED NOT NULL,      -- 0 for yearly/all-time, 1-12 otherwise
    week          TINYINT UNSIGNED NOT NULL,      -- 0 for non-weekly, 1-53 for weekly

    /* ──────────── Metrics (signed for negative deltas) ──────────── */
    uploads       BIGINT NOT NULL DEFAULT 0,      -- Photo count delta
    tags          BIGINT NOT NULL DEFAULT 0,      -- Total tags (objects + materials + brands)
    litter        BIGINT NOT NULL DEFAULT 0,      -- Total litter items (objects only)
    brands        BIGINT NOT NULL DEFAULT 0,      -- Brand tag count
    materials     BIGINT NOT NULL DEFAULT 0,      -- Material tag count
    custom_tags   BIGINT NOT NULL DEFAULT 0,      -- Custom tag count
    xp            BIGINT NOT NULL DEFAULT 0,      -- Experience points

    /* ──────────── Metadata ──────────── */
    created_at    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    /* ──────────── Keys ──────────── */
    PRIMARY KEY (timescale, location_type, location_id, user_id, year, month, week, bucket_date),

    -- User timeline queries (when user_id > 0)
    INDEX idx_user_timeline (user_id, timescale, bucket_date),

    -- Date range scans across locations
    INDEX idx_date_range (timescale, bucket_date, location_type),

    /* ──────────── Constraints ──────────── */
    CONSTRAINT chk_timescale CHECK (timescale BETWEEN 0 AND 4),
    CONSTRAINT chk_location_type CHECK (location_type BETWEEN 0 AND 3),
    -- Enforce user rows must be global scope
    CONSTRAINT chk_user_location CHECK (user_id = 0 OR (location_type = 0 AND location_id = 0))

) ENGINE=InnoDB;
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS metrics');
    }
};
