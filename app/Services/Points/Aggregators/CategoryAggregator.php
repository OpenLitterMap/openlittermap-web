<?php

namespace App\Services\Points\Aggregators;

use Illuminate\Support\Facades\DB;

class CategoryAggregator
{
    /**
     * Aggregate category breakdown (objects only, no brands/materials)
     */
    public function aggregate(string $whereSql, array $bindings): array
    {
        $results = DB::select("
            SELECT
                c.key,
                SUM(pt.quantity) as count
            FROM photos p
            JOIN photo_tags pt ON p.id = pt.photo_id
            JOIN categories c ON pt.category_id = c.id
            WHERE {$whereSql}
                AND pt.litter_object_id IS NOT NULL
            GROUP BY c.id, c.key
            ORDER BY count DESC
        ", $bindings);

        return array_map(fn($row) => [
            'key' => $row->key,
            'name' => $this->formatName($row->key),
            'count' => (int)$row->count,
            'color' => $this->getCategoryColor($row->key),
        ], $results);
    }

    /**
     * Aggregate from temporary table
     */
    public function aggregateFromTable(string $table): array
    {
        $results = DB::select("
            SELECT
                c.key,
                SUM(pt.quantity) as count
            FROM {$table} p
            JOIN photo_tags pt ON p.id = pt.photo_id
            JOIN categories c ON pt.category_id = c.id
            WHERE pt.litter_object_id IS NOT NULL
            GROUP BY c.id, c.key
            ORDER BY count DESC
        ");

        return array_map(fn($row) => [
            'key' => $row->key,
            'name' => $this->formatName($row->key),
            'count' => (int)$row->count,
            'color' => $this->getCategoryColor($row->key),
        ], $results);
    }

    /**
     * Format category name
     */
    private function formatName(string $key): string
    {
        return ucfirst(str_replace('_', ' ', $key));
    }

    /**
     * Get category color for visualization
     */
    private function getCategoryColor(string $key): string
    {
        $colors = [
            'smoking' => '#ff6b6b',
            'food' => '#4ecdc4',
            'alcohol' => '#45b7d1',
            'coffee' => '#96ceb4',
            'soft_drinks' => '#ffeaa7',
            'drugs' => '#fd79a8',
            'sanitary' => '#dfe6e9',
            'coastal' => '#00b894',
            'dumping' => '#636e72',
            'industrial' => '#2d3436',
            'art' => '#e17055',
            'dogshit' => '#8b6f47',
            'other' => '#74b9ff',
        ];

        return $colors[$key] ?? '#95a5a6';
    }
}
