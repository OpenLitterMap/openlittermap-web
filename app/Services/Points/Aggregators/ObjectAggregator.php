<?php

namespace App\Services\Points\Aggregators;

use Illuminate\Support\Facades\DB;

class ObjectAggregator
{
    /**
     * Aggregate top litter objects
     */
    public function aggregate(string $whereSql, array $bindings): array
    {
        $results = DB::select("
            SELECT
                lo.key,
                SUM(pt.quantity) as count
            FROM photos p
            JOIN photo_tags pt ON p.id = pt.photo_id
            JOIN litter_objects lo ON pt.litter_object_id = lo.id
            WHERE {$whereSql}
            GROUP BY lo.id, lo.key
            ORDER BY count DESC
            LIMIT 50
        ", $bindings);

        return array_map(fn($row) => [
            'key' => $row->key,
            'name' => $this->formatName($row->key),
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
                lo.key,
                SUM(pt.quantity) as count
            FROM {$table} p
            JOIN photo_tags pt ON p.id = pt.photo_id
            JOIN litter_objects lo ON pt.litter_object_id = lo.id
            GROUP BY lo.id, lo.key
            ORDER BY count DESC
            LIMIT 50
        ");

        return array_map(fn($row) => [
            'key' => $row->key,
            'name' => $this->formatName($row->key),
            'count' => (int)$row->count,
        ], $results);
    }

    /**
     * Format object name
     */
    private function formatName(string $key): string
    {
        return ucfirst(str_replace('_', ' ', $key));
    }
}
