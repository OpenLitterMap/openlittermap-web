<?php


namespace App\Services\Points\Aggregators;

use Illuminate\Support\Facades\DB;

class BrandAggregator
{
    /**
     * Aggregate top brands
     */
    public function aggregate(string $whereSql, array $bindings): array
    {
        // Assuming brands are stored in photo_tag_extra_tags or a brands table exists
        $results = DB::select("
            SELECT
                ptet.tag_type_id as brand_id,
                SUM(ptet.quantity) as count
            FROM photos p
            JOIN photo_tags pt ON p.id = pt.photo_id
            JOIN photo_tag_extra_tags ptet ON pt.id = ptet.photo_tag_id
            WHERE {$whereSql} AND ptet.tag_type = 'brand'
            GROUP BY ptet.tag_type_id
            ORDER BY count DESC
            LIMIT 30
        ", $bindings);

        // Try to get brand names if brands table exists
        $brandIds = array_column($results, 'brand_id');
        $brandNames = [];

        if (!empty($brandIds)) {
            try {
                $brands = DB::table('brands')
                    ->whereIn('id', $brandIds)
                    ->pluck('key', 'id')
                    ->toArray();
                $brandNames = $brands;
            } catch (\Exception $e) {
                // Brands table might not exist
            }
        }

        return array_map(fn($row) => [
            'key' => $brandNames[$row->brand_id] ?? "brand_{$row->brand_id}",
            'name' => $this->formatName($brandNames[$row->brand_id] ?? "Brand {$row->brand_id}"),
            'count' => (int)$row->count,
        ], $results);
    }

    /**
     * Aggregate from temporary table
     */
    public function aggregateFromTable(string $table): array
    {
        $results = DB::select("
            SELECT
                ptet.tag_type_id as brand_id,
                SUM(ptet.quantity) as count
            FROM {$table} p
            JOIN photo_tags pt ON p.id = pt.photo_id
            JOIN photo_tag_extra_tags ptet ON pt.id = ptet.photo_tag_id
            WHERE ptet.tag_type = 'brand'
            GROUP BY ptet.tag_type_id
            ORDER BY count DESC
            LIMIT 30
        ");

        $brandIds = array_column($results, 'brand_id');
        $brandNames = [];

        if (!empty($brandIds)) {
            try {
                $brands = DB::table('brands')
                    ->whereIn('id', $brandIds)
                    ->pluck('key', 'id')
                    ->toArray();
                $brandNames = $brands;
            } catch (\Exception $e) {
                // Brands table might not exist
            }
        }

        return array_map(fn($row) => [
            'key' => $brandNames[$row->brand_id] ?? "brand_{$row->brand_id}",
            'name' => $this->formatName($brandNames[$row->brand_id] ?? "Brand {$row->brand_id}"),
            'count' => (int)$row->count,
        ], $results);
    }

    /**
     * Format brand name
     */
    private function formatName(string $key): string
    {
        return ucfirst(str_replace('_', ' ', $key));
    }
}
