<?php

namespace App\Services\Points\Aggregators;

use Illuminate\Support\Facades\DB;

class MaterialAggregator
{
    /**
     * Aggregate top materials
     */
    public function aggregate(string $whereSql, array $bindings): array
    {
        $results = DB::select("
            SELECT
                ptet.tag_type_id as material_id,
                SUM(ptet.quantity) as count
            FROM photos p
            JOIN photo_tags pt ON p.id = pt.photo_id
            JOIN photo_tag_extra_tags ptet ON pt.id = ptet.photo_tag_id
            WHERE {$whereSql} AND ptet.tag_type = 'material'
            GROUP BY ptet.tag_type_id
            ORDER BY count DESC
            LIMIT 20
        ", $bindings);

        // Try to get material names if materials table exists
        $materialIds = array_column($results, 'material_id');
        $materialNames = [];

        if (!empty($materialIds)) {
            try {
                $materials = DB::table('materials')
                    ->whereIn('id', $materialIds)
                    ->pluck('key', 'id')
                    ->toArray();
                $materialNames = $materials;
            } catch (\Exception $e) {
                // Materials table might not exist
            }
        }

        return array_map(fn($row) => [
            'key' => $materialNames[$row->material_id] ?? "material_{$row->material_id}",
            'name' => $this->formatName($materialNames[$row->material_id] ?? "Material {$row->material_id}"),
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
                ptet.tag_type_id as material_id,
                SUM(ptet.quantity) as count
            FROM {$table} p
            JOIN photo_tags pt ON p.id = pt.photo_id
            JOIN photo_tag_extra_tags ptet ON pt.id = ptet.photo_tag_id
            WHERE ptet.tag_type = 'material'
            GROUP BY ptet.tag_type_id
            ORDER BY count DESC
            LIMIT 20
        ");

        $materialIds = array_column($results, 'material_id');
        $materialNames = [];

        if (!empty($materialIds)) {
            try {
                $materials = DB::table('materials')
                    ->whereIn('id', $materialIds)
                    ->pluck('key', 'id')
                    ->toArray();
                $materialNames = $materials;
            } catch (\Exception $e) {
                // Materials table might not exist
            }
        }

        return array_map(fn($row) => [
            'key' => $materialNames[$row->material_id] ?? "material_{$row->material_id}",
            'name' => $this->formatName($materialNames[$row->material_id] ?? "Material {$row->material_id}"),
            'count' => (int)$row->count,
        ], $results);
    }

    /**
     * Format material name
     */
    private function formatName(string $key): string
    {
        return ucfirst(str_replace('_', ' ', $key));
    }
}
