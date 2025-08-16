<?php

namespace App\Services\Points;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PointsStatsService
{
    private const MAX_TEMP_TABLE_ROWS = 100000;
    private const SAMPLE_RATE = 10; // 10% sampling for large datasets

    /**
     * Get aggregated stats with intelligent caching and strategy selection
     */
    public function getStats(array $params): array
    {
        $cacheKey = $this->generateCacheKey($params);
        $cacheTTL = $this->getCacheTTL($params['zoom']);

        return Cache::remember($cacheKey, $cacheTTL, function() use ($params) {
            // Check if we need to limit results
            $totalCount = $this->getFilteredPhotoCount($params);

            if ($totalCount > self::MAX_TEMP_TABLE_ROWS) {
                // Use sampling for very large datasets
                return $this->sampledAggregate($params, $totalCount);
            } elseif ($totalCount > 5000) {
                // Use TEMP table for medium datasets
                return $this->tempTableAggregate($params, $totalCount);
            } else {
                // Use direct queries for small datasets
                return $this->directAggregate($params);
            }
        });
    }

    /**
     * Get count of filtered photos to determine strategy
     */
    private function getFilteredPhotoCount(array $params): int
    {
        [$whereSql, $bindings] = $this->buildOptimizedWhere($params);

        $result = DB::selectOne("
            SELECT COUNT(*) as count
            FROM photos p
            WHERE {$whereSql}
        ", $bindings);

        return (int) $result->count;
    }

    /**
     * TEMP table approach with correct aggregations
     */
    private function tempTableAggregate(array $params, int $totalCount): array
    {
        [$whereSql, $bindings] = $this->buildOptimizedWhere($params);
        [$bucketExpr, $bucketFormat] = $this->getTimeBucket($params);

        $truncated = $totalCount > self::MAX_TEMP_TABLE_ROWS;

        try {
            return DB::transaction(function() use ($whereSql, $bindings, $bucketExpr, $truncated) {
                // Create TEMP table with MEMORY engine for speed
                DB::statement('DROP TEMPORARY TABLE IF EXISTS tmp_filtered_photos');
                DB::statement("
                    CREATE TEMPORARY TABLE tmp_filtered_photos (
                        id INT UNSIGNED NOT NULL,
                        user_id INT UNSIGNED,
                        team_id INT UNSIGNED,
                        datetime DATETIME,
                        verified TINYINT,
                        remaining BOOLEAN,
                        total_litter INT,
                        PRIMARY KEY (id),
                        INDEX idx_user_team (user_id, team_id),
                        INDEX idx_datetime (datetime)
                    ) ENGINE=MEMORY
                ");

                // Populate with filtered photos (with limit if needed)
                $limitClause = $truncated ? 'LIMIT ' . self::MAX_TEMP_TABLE_ROWS : '';
                DB::statement("
                    INSERT INTO tmp_filtered_photos
                    SELECT p.id, p.user_id, p.team_id, p.datetime,
                           p.verified, p.remaining, p.total_litter
                    FROM photos p
                    WHERE {$whereSql}
                    ORDER BY p.datetime DESC
                    {$limitClause}
                ", $bindings);

                $results = $this->runCorrectAggregations($bucketExpr);

                if ($truncated) {
                    $results['meta'] = ['truncated' => true, 'limit' => self::MAX_TEMP_TABLE_ROWS];
                }

                return $results;
            });
        } catch (\Exception $e) {
            Log::warning('TEMP table aggregation failed, falling back', ['error' => $e->getMessage()]);
            return $this->directAggregate($params);
        }
    }

    /**
     * Run aggregations with CORRECT join logic (fixes ChatGPT identified issues)
     */
    private function runCorrectAggregations(string $bucketExpr): array
    {
        // 1. Core counts
        $counts = DB::selectOne("
            SELECT
                COUNT(*) AS photos,
                COUNT(DISTINCT user_id) AS users,
                COUNT(DISTINCT team_id) AS teams,
                SUM(CASE WHEN remaining = 0 THEN 1 ELSE 0 END) AS picked_up,
                SUM(CASE WHEN remaining = 1 THEN 1 ELSE 0 END) AS not_picked_up
            FROM tmp_filtered_photos
        ");

        // 2. CORRECTED totals (avoiding duplicate counting, brands don't count toward total_tags)
        $totals = DB::selectOne("
            WITH pt AS (
                SELECT pt.id, pt.quantity
                FROM photo_tags pt
                JOIN tmp_filtered_photos fp ON fp.id = pt.photo_id
            ),
            ext AS (
                SELECT photo_tag_id,
                       SUM(CASE WHEN tag_type = 'material' THEN quantity ELSE 0 END) AS material_qty,
                       SUM(CASE WHEN tag_type = 'custom_tag' THEN quantity ELSE 0 END) AS custom_qty
                FROM photo_tag_extra_tags
                WHERE photo_tag_id IN (SELECT id FROM pt)
                GROUP BY photo_tag_id
            )
            SELECT
                COALESCE(SUM(pt.quantity), 0) AS total_objects,
                COALESCE(SUM(pt.quantity), 0) + COALESCE(SUM(ext.material_qty), 0) AS total_tags
            FROM pt
            LEFT JOIN ext ON ext.photo_tag_id = pt.id
        ");

        // 3. CORRECTED categories (including only materials, not brands)
        $categories = DB::select("
            WITH pt AS (
                SELECT pt.id, pt.category_id, pt.quantity
                FROM photo_tags pt
                JOIN tmp_filtered_photos fp ON fp.id = pt.photo_id
            ),
            ext AS (
                SELECT photo_tag_id,
                       SUM(CASE WHEN tag_type = 'material' THEN quantity ELSE 0 END) AS material_qty
                FROM photo_tag_extra_tags
                WHERE photo_tag_id IN (SELECT id FROM pt)
                GROUP BY photo_tag_id
            )
            SELECT c.key, SUM(pt.quantity + COALESCE(ext.material_qty, 0)) AS qty
            FROM pt
            JOIN categories c ON c.id = pt.category_id
            LEFT JOIN ext ON ext.photo_tag_id = pt.id
            GROUP BY c.key
            ORDER BY qty DESC
            LIMIT 12
        ");

        // 4. Objects (base quantity only)
        $objects = DB::select("
            SELECT lo.key, SUM(pt.quantity) AS qty
            FROM tmp_filtered_photos fp
            JOIN photo_tags pt ON pt.photo_id = fp.id
            JOIN litter_objects lo ON lo.id = pt.litter_object_id
            GROUP BY lo.key
            ORDER BY qty DESC
            LIMIT 20
        ");

        // 5. Materials
        $materials = DB::select("
            SELECT m.key, SUM(et.quantity) AS qty
            FROM tmp_filtered_photos fp
            JOIN photo_tags pt ON pt.photo_id = fp.id
            JOIN photo_tag_extra_tags et ON et.photo_tag_id = pt.id AND et.tag_type = 'material'
            JOIN materials m ON m.id = et.tag_type_id
            GROUP BY m.key
            ORDER BY qty DESC
            LIMIT 12
        ");

        // 6. CORRECTED Brands (using SUM not COUNT)
        $brands = DB::select("
            SELECT b.key, SUM(et.quantity) AS qty
            FROM tmp_filtered_photos fp
            JOIN photo_tags pt ON pt.photo_id = fp.id
            JOIN photo_tag_extra_tags et ON et.photo_tag_id = pt.id AND et.tag_type = 'brand'
            JOIN brandslist b ON b.id = et.tag_type_id
            GROUP BY b.key
            ORDER BY qty DESC
            LIMIT 12
        ");

        // 7. Custom tags
        $custom = DB::select("
            SELECT ctn.key, SUM(et.quantity) AS qty
            FROM tmp_filtered_photos fp
            JOIN photo_tags pt ON pt.photo_id = fp.id
            JOIN photo_tag_extra_tags et ON et.photo_tag_id = pt.id AND et.tag_type = 'custom_tag'
            JOIN custom_tags_new ctn ON ctn.id = et.tag_type_id
            GROUP BY ctn.key
            ORDER BY qty DESC
            LIMIT 12
        ");

        // 8. Time histogram with proper ordering
        $histogram = DB::select("
            SELECT {$bucketExpr} AS bucket, COUNT(*) AS photos
            FROM tmp_filtered_photos
            GROUP BY bucket
            ORDER BY bucket
        ");

        return [
            'counts' => array_merge(
                (array)$counts,
                ['total_objects' => (int)$totals->total_objects, 'total_tags' => (int)$totals->total_tags]
            ),
            'by_category' => array_map(fn($r) => ['key' => $r->key, 'qty' => (int)$r->qty], $categories),
            'by_object' => array_map(fn($r) => ['key' => $r->key, 'qty' => (int)$r->qty], $objects),
            'materials' => array_map(fn($r) => ['key' => $r->key, 'qty' => (int)$r->qty], $materials),
            'brands' => array_map(fn($r) => ['key' => $r->key, 'qty' => (int)$r->qty], $brands),
            'custom_tags' => array_map(fn($r) => ['key' => $r->key, 'qty' => (int)$r->qty], $custom),
            'time_histogram' => array_map(fn($r) => ['bucket' => $r->bucket, 'photos' => (int)$r->photos], $histogram)
        ];
    }

    /**
     * Direct aggregation for small datasets with CORRECTED logic
     */
    private function directAggregate(array $params): array
    {
        [$whereSql, $bindings] = $this->buildOptimizedWhere($params);
        [$bucketExpr, $bucketFormat] = $this->getTimeBucket($params);

        // Single query with multiple CTEs
        $results = DB::select("
            WITH filtered_photos AS (
                SELECT p.id, p.user_id, p.team_id, p.datetime,
                       p.verified, p.remaining, p.total_litter
                FROM photos p
                WHERE {$whereSql}
            ),
            photo_counts AS (
                SELECT
                    COUNT(*) as photos,
                    COUNT(DISTINCT user_id) as users,
                    COUNT(DISTINCT team_id) as teams,
                    SUM(CASE WHEN remaining = 0 THEN 1 ELSE 0 END) as picked_up,
                    SUM(CASE WHEN remaining = 1 THEN 1 ELSE 0 END) as not_picked_up
                FROM filtered_photos
            ),
            tag_totals AS (
                WITH pt AS (
                    SELECT pt.id, pt.quantity
                    FROM photo_tags pt
                    JOIN filtered_photos fp ON fp.id = pt.photo_id
                ),
                ext AS (
                    SELECT photo_tag_id,
                           SUM(CASE WHEN tag_type = 'material' THEN quantity ELSE 0 END) AS material_qty
                    FROM photo_tag_extra_tags
                    WHERE photo_tag_id IN (SELECT id FROM pt)
                    GROUP BY photo_tag_id
                )
                SELECT
                    COALESCE(SUM(pt.quantity), 0) AS total_objects,
                    COALESCE(SUM(pt.quantity), 0) + COALESCE(SUM(ext.material_qty), 0) AS total_tags
                FROM pt
                LEFT JOIN ext ON ext.photo_tag_id = pt.id
            )
            SELECT
                pc.*,
                tt.total_objects,
                tt.total_tags
            FROM photo_counts pc
            CROSS JOIN tag_totals tt
        ", $bindings);

        $counts = (array) $results[0];

        // Get detailed breakdowns
        $categories = $this->getCorrectedCategoryStats($whereSql, $bindings);
        $objects = $this->getObjectStats($whereSql, $bindings);
        $materials = $this->getMaterialStats($whereSql, $bindings);
        $brands = $this->getCorrectedBrandStats($whereSql, $bindings);
        $custom = $this->getCustomTagStats($whereSql, $bindings);
        $histogram = $this->getTimeHistogram($whereSql, $bindings, $bucketExpr);

        return [
            'counts' => $counts,
            'by_category' => $categories,
            'by_object' => $objects,
            'materials' => $materials,
            'brands' => $brands,
            'custom_tags' => $custom,
            'time_histogram' => $histogram
        ];
    }

    /**
     * CORRECTED sampled aggregation (no TABLESAMPLE in MySQL)
     */
    private function sampledAggregate(array $params, int $totalCount): array
    {
        [$whereSql, $bindings] = $this->buildOptimizedWhere($params);

        // Use deterministic sampling by ID modulo
        $sampleWhere = $whereSql . " AND MOD(p.id, " . self::SAMPLE_RATE . ") = 0";

        $counts = DB::selectOne("
            SELECT
                COUNT(*) * " . self::SAMPLE_RATE . " AS photos,
                COUNT(DISTINCT user_id) AS users,
                COUNT(DISTINCT team_id) AS teams,
                SUM(remaining = 0) * " . self::SAMPLE_RATE . " AS picked_up,
                SUM(remaining = 1) * " . self::SAMPLE_RATE . " AS not_picked_up
            FROM photos p
            WHERE {$sampleWhere}
        ", $bindings);

        $categories = DB::select("
            WITH pt AS (
                SELECT pt.id, pt.category_id, pt.quantity
                FROM photo_tags pt
                JOIN photos p ON p.id = pt.photo_id
                WHERE {$sampleWhere}
            ),
            ext AS (
                SELECT photo_tag_id,
                       SUM(CASE WHEN tag_type = 'material' THEN quantity ELSE 0 END) AS material_qty
                FROM photo_tag_extra_tags
                WHERE photo_tag_id IN (SELECT id FROM pt)
                GROUP BY photo_tag_id
            )
            SELECT c.key, SUM(pt.quantity + COALESCE(ext.material_qty, 0)) * " . self::SAMPLE_RATE . " AS qty
            FROM pt
            JOIN categories c ON c.id = pt.category_id
            LEFT JOIN ext ON ext.photo_tag_id = pt.id
            GROUP BY c.key
            ORDER BY qty DESC
            LIMIT 10
        ", $bindings);

        return [
            'counts' => (array) $counts,
            'by_category' => array_map(fn($r) => ['key' => $r->key, 'qty' => (int)$r->qty], $categories),
            'meta' => [
                'sampling' => true,
                'sample_rate' => (100 / self::SAMPLE_RATE) . '%',
                'estimated_total' => $totalCount,
                'note' => 'Results are statistical estimates based on deterministic sampling'
            ]
        ];
    }

    /**
     * Build optimized WHERE clause - MATCHING YOUR EXISTING POINTSCONTROLLER
     */
    private function buildOptimizedWhere(array $params): array
    {
        $where = [];
        $bindings = [];

        // Spatial filter using MBRContains (matching your PointsController exactly)
        $bbox = $params['bbox'];
        $where[] = "MBRContains(ST_GeomFromText(?, 4326, 'axis-order=long-lat'), p.geom)";
        $bindings[] = sprintf('POLYGON((%F %F, %F %F, %F %F, %F %F, %F %F))',
            $bbox['left'], $bbox['bottom'],
            $bbox['right'], $bbox['bottom'],
            $bbox['right'], $bbox['top'],
            $bbox['left'], $bbox['top'],
            $bbox['left'], $bbox['bottom']
        );

        // Date filters
        if (!empty($params['year'])) {
            $where[] = "p.datetime BETWEEN ? AND ?";
            $bindings[] = "{$params['year']}-01-01 00:00:00";
            $bindings[] = "{$params['year']}-12-31 23:59:59";
        } else {
            if (!empty($params['from'])) {
                $where[] = "p.datetime >= ?";
                $bindings[] = $params['from'] . ' 00:00:00';
            }
            if (!empty($params['to'])) {
                $where[] = "p.datetime <= ?";
                $bindings[] = $params['to'] . ' 23:59:59';
            }
        }

        // Username filter with EXISTS
        if (!empty($params['username'])) {
            $where[] = "EXISTS (
                SELECT 1 FROM users u
                WHERE u.id = p.user_id
                AND u.show_username_maps = 1
                AND u.username = ?
            )";
            $bindings[] = $params['username'];
        }

        // Tag filters
        [$tagExists, $tagBindings] = $this->buildTagExistsOptimized($params);
        if ($tagExists) {
            $where[] = $tagExists;
            $bindings = array_merge($bindings, $tagBindings);
        }

        return [implode(' AND ', $where), $bindings];
    }

    /**
     * Build optimized tag EXISTS clauses
     */
    private function buildTagExistsOptimized(array $params): array
    {
        $clauses = [];
        $bindings = [];

        // Categories AND objects must be in same PhotoTag
        if (!empty($params['categories']) && !empty($params['litter_objects'])) {
            $catPlaceholders = implode(',', array_fill(0, count($params['categories']), '?'));
            $objPlaceholders = implode(',', array_fill(0, count($params['litter_objects']), '?'));

            $clauses[] = "EXISTS (
                SELECT 1 FROM photo_tags pt
                JOIN categories c ON c.id = pt.category_id
                JOIN litter_objects lo ON lo.id = pt.litter_object_id
                WHERE pt.photo_id = p.id
                AND c.key IN ({$catPlaceholders})
                AND lo.key IN ({$objPlaceholders})
            )";

            $bindings = array_merge($bindings, $params['categories'], $params['litter_objects']);
        } else {
            // Handle separately with OR
            if (!empty($params['categories'])) {
                $placeholders = implode(',', array_fill(0, count($params['categories']), '?'));
                $clauses[] = "EXISTS (
                    SELECT 1 FROM photo_tags pt
                    JOIN categories c ON c.id = pt.category_id
                    WHERE pt.photo_id = p.id
                    AND c.key IN ({$placeholders})
                )";
                $bindings = array_merge($bindings, $params['categories']);
            }

            if (!empty($params['litter_objects'])) {
                $placeholders = implode(',', array_fill(0, count($params['litter_objects']), '?'));
                $clauses[] = "EXISTS (
                    SELECT 1 FROM photo_tags pt
                    JOIN litter_objects lo ON lo.id = pt.litter_object_id
                    WHERE pt.photo_id = p.id
                    AND lo.key IN ({$placeholders})
                )";
                $bindings = array_merge($bindings, $params['litter_objects']);
            }
        }

        // Extra tags
        if (!empty($params['materials'])) {
            $placeholders = implode(',', array_fill(0, count($params['materials']), '?'));
            $clauses[] = "EXISTS (
                SELECT 1 FROM photo_tags pt
                JOIN photo_tag_extra_tags et ON et.photo_tag_id = pt.id
                JOIN materials m ON m.id = et.tag_type_id
                WHERE pt.photo_id = p.id
                AND et.tag_type = 'material'
                AND m.key IN ({$placeholders})
            )";
            $bindings = array_merge($bindings, $params['materials']);
        }

        if (!empty($params['brands'])) {
            $placeholders = implode(',', array_fill(0, count($params['brands']), '?'));
            $clauses[] = "EXISTS (
                SELECT 1 FROM photo_tags pt
                JOIN photo_tag_extra_tags et ON et.photo_tag_id = pt.id
                JOIN brandslist b ON b.id = et.tag_type_id
                WHERE pt.photo_id = p.id
                AND et.tag_type = 'brand'
                AND b.key IN ({$placeholders})
            )";
            $bindings = array_merge($bindings, $params['brands']);
        }

        if (!empty($params['custom_tags'])) {
            $placeholders = implode(',', array_fill(0, count($params['custom_tags']), '?'));
            $clauses[] = "EXISTS (
                SELECT 1 FROM photo_tags pt
                JOIN custom_tags_new ctn ON ctn.id = pt.custom_tag_primary_id
                WHERE pt.photo_id = p.id
                AND ctn.approved = 1
                AND ctn.key IN ({$placeholders})
            )";
            $bindings = array_merge($bindings, $params['custom_tags']);
        }

        if (empty($clauses)) {
            return ['', []];
        }

        return ['(' . implode(' OR ', $clauses) . ')', $bindings];
    }

    /**
     * CORRECTED time bucket with proper weekly handling
     */
    // PointsStatsService.php

    private function getTimeBucket(array $params): array
    {
        $from = $params['from'] ?? null;
        $to   = $params['to']   ?? null;

        if ($from && $to) {
            $start = new CarbonImmutable($from . ' 00:00:00');
            $end   = new CarbonImmutable($to   . ' 23:59:59');
            // Difference in *whole days*; same day => 0
            $spanDays = $start->startOfDay()->diffInDays($end->startOfDay());

            if ($spanDays === 0) {
                // Hourly only when range is a single day
                return ["DATE_FORMAT(datetime, '%Y-%m-%d %H:00')", '%Y-%m-%d %H:00'];
            }
            if ($spanDays <= 31) {
                // Daily for up to ~1 month
                return ["DATE_FORMAT(datetime, '%Y-%m-%d')", '%Y-%m-%d'];
            }
            if ($spanDays <= 180) {
                // Weekly (ISO week) → Monday date like 2025-06-16
                return [
                    "DATE_FORMAT(STR_TO_DATE(CONCAT(YEARWEEK(datetime, 3),' Monday'), '%X%V %W'), '%Y-%m-%d')",
                    '%Y-%m-%d'
                ];
            }
        }

        // Monthly default: first day of month
        return ["DATE_FORMAT(datetime, '%Y-%m-01')", '%Y-%m-%d'];
    }

    /**
     * Dynamic cache TTL based on zoom level
     */
    private function getCacheTTL(int $zoom): int
    {
        return match(true) {
            $zoom >= 19 => 300,  // 5 minutes for high zoom
            $zoom >= 17 => 180,  // 3 minutes for medium zoom
            default => 60        // 1 minute for low zoom
        };
    }

    /**
     * Generate cache key with tile-snapping
     */
    public function generateCacheKey(array $params): string
    {
        $zoom = $params['zoom'];
        $bbox = $this->snapToTile($params['bbox'], $zoom);

        $keyParts = [
            'points_stats_v2',
            'z' . $zoom,
            'b' . implode('_', array_map(fn($v) => round($v, 4), $bbox)),
            'f' . md5(json_encode([
                'categories' => $params['categories'] ?? [],
                'litter_objects' => $params['litter_objects'] ?? [],
                'materials' => $params['materials'] ?? [],
                'brands' => $params['brands'] ?? [],
                'custom_tags' => $params['custom_tags'] ?? [],
                'from' => $params['from'] ?? null,
                'to' => $params['to'] ?? null,
                'year' => $params['year'] ?? null,
                'username' => $params['username'] ?? null
            ]))
        ];

        return implode(':', $keyParts);
    }

    /**
     * Snap bbox to tile grid - FIXED for proper snapping
     */
    public function snapToTile(array $bbox, int $zoom): array
    {
        $n = pow(2, $zoom);

        // Convert lon/lat to tile numbers
        $xMin = floor(($bbox['left'] + 180) / 360 * $n);
        $xMax = floor(($bbox['right'] + 180) / 360 * $n);

        // For latitude, use the Mercator projection formula
        $latRadTop = deg2rad($bbox['top']);
        $yMin = floor($n * (1 - (log(tan($latRadTop) + 1/cos($latRadTop)) / pi())) / 2);

        $latRadBottom = deg2rad($bbox['bottom']);
        $yMax = floor($n * (1 - (log(tan($latRadBottom) + 1/cos($latRadBottom)) / pi())) / 2);

        // Convert back to lat/lon (snapped to tile boundaries)
        return [
            'left' => $xMin / $n * 360 - 180,
            'right' => ($xMax + 1) / $n * 360 - 180,
            'top' => rad2deg(atan(sinh(pi() * (1 - 2 * $yMin / $n)))),
            'bottom' => rad2deg(atan(sinh(pi() * (1 - 2 * ($yMax + 1) / $n))))
        ];
    }

    // CORRECTED helper methods

    private function getCorrectedCategoryStats(string $whereSql, array $bindings): array
    {
        return DB::select("
            WITH pt AS (
                SELECT pt.id, pt.category_id, pt.quantity
                FROM photo_tags pt
                JOIN photos p ON p.id = pt.photo_id
                WHERE {$whereSql}
            ),
            ext AS (
                SELECT photo_tag_id,
                       SUM(CASE WHEN tag_type = 'material' THEN quantity ELSE 0 END) AS material_qty
                FROM photo_tag_extra_tags
                WHERE photo_tag_id IN (SELECT id FROM pt)
                GROUP BY photo_tag_id
            )
            SELECT c.key, SUM(pt.quantity + COALESCE(ext.material_qty, 0)) AS qty
            FROM pt
            JOIN categories c ON c.id = pt.category_id
            LEFT JOIN ext ON ext.photo_tag_id = pt.id
            GROUP BY c.key
            ORDER BY qty DESC
            LIMIT 12
        ", $bindings);
    }

    private function getObjectStats(string $whereSql, array $bindings): array
    {
        return DB::select("
            SELECT lo.key, SUM(pt.quantity) AS qty
            FROM photos p
            JOIN photo_tags pt ON pt.photo_id = p.id
            JOIN litter_objects lo ON lo.id = pt.litter_object_id
            WHERE {$whereSql}
            GROUP BY lo.key
            ORDER BY qty DESC
            LIMIT 20
        ", $bindings);
    }

    private function getMaterialStats(string $whereSql, array $bindings): array
    {
        return DB::select("
            SELECT m.key, SUM(et.quantity) AS qty
            FROM photos p
            JOIN photo_tags pt ON pt.photo_id = p.id
            JOIN photo_tag_extra_tags et ON et.photo_tag_id = pt.id
            JOIN materials m ON m.id = et.tag_type_id
            WHERE {$whereSql} AND et.tag_type = 'material'
            GROUP BY m.key
            ORDER BY qty DESC
            LIMIT 12
        ", $bindings);
    }

    private function getCorrectedBrandStats(string $whereSql, array $bindings): array
    {
        return DB::select("
            SELECT b.key, SUM(et.quantity) AS qty
            FROM photos p
            JOIN photo_tags pt ON pt.photo_id = p.id
            JOIN photo_tag_extra_tags et ON et.photo_tag_id = pt.id
            JOIN brandslist b ON b.id = et.tag_type_id
            WHERE {$whereSql} AND et.tag_type = 'brand'
            GROUP BY b.key
            ORDER BY qty DESC
            LIMIT 12
        ", $bindings);
    }

    private function getCustomTagStats(string $whereSql, array $bindings): array
    {
        return DB::select("
            SELECT ctn.key, SUM(et.quantity) AS qty
            FROM photos p
            JOIN photo_tags pt ON pt.photo_id = p.id
            JOIN photo_tag_extra_tags et ON et.photo_tag_id = pt.id
            JOIN custom_tags_new ctn ON ctn.id = et.tag_type_id
            WHERE {$whereSql} AND et.tag_type = 'custom_tag'
            GROUP BY ctn.key
            ORDER BY qty DESC
            LIMIT 12
        ", $bindings);
    }

    private function getTimeHistogram(string $whereSql, array $bindings, string $bucketExpr): array
    {
        return DB::select("
            SELECT {$bucketExpr} AS bucket, COUNT(*) AS photos
            FROM photos p
            WHERE {$whereSql}
            GROUP BY bucket
            ORDER BY bucket
        ", $bindings);
    }
}
