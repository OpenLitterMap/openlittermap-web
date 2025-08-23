<?php

namespace App\Services\Points\Aggregators;

use Illuminate\Support\Facades\DB;

class MetadataAggregator
{
    /**
     * Aggregate metadata from WHERE clause
     */
    public function aggregate(string $whereSql, array $bindings): array
    {
        // Get basic counts
        $photoStats = DB::selectOne("
            SELECT
                COUNT(*) as photos,
                COUNT(DISTINCT user_id) as users,
                COUNT(DISTINCT team_id) as teams,
                SUM(total_litter) as total_objects,
                SUM(total_brands) as total_brands,
                SUM(CASE WHEN remaining = 0 THEN 1 ELSE 0 END) as picked_up,
                SUM(CASE WHEN remaining = 1 THEN 1 ELSE 0 END) as not_picked_up
            FROM photos
            WHERE {$whereSql}
        ", $bindings);

        // Get total tags from photo_tags
        $tagStats = DB::selectOne("
            SELECT
                SUM(pt.quantity) as total_tags
            FROM photos p
            JOIN photo_tags pt ON p.id = pt.photo_id
            WHERE {$whereSql}
        ", $bindings);

        return [
            'total_photos' => (int)$photoStats->photos,
            'total_tags' => (int)$tagStats->total_tags,
            'total_objects' => (int)$photoStats->total_objects,
            'total_brands' => (int)$photoStats->total_brands,
            'total_users' => (int)$photoStats->users,
            'total_teams' => (int)$photoStats->teams,
            'picked_up' => (int)$photoStats->picked_up,
            'not_picked_up' => (int)$photoStats->not_picked_up,
        ];
    }

    /**
     * Aggregate metadata from temporary table
     */
    public function aggregateFromTable(string $table): array
    {
        $photoStats = DB::selectOne("
            SELECT
                COUNT(*) as photos,
                COUNT(DISTINCT user_id) as users,
                COUNT(DISTINCT team_id) as teams,
                SUM(total_litter) as total_objects,
                SUM(total_brands) as total_brands,
                SUM(CASE WHEN remaining = 0 THEN 1 ELSE 0 END) as picked_up,
                SUM(CASE WHEN remaining = 1 THEN 1 ELSE 0 END) as not_picked_up
            FROM {$table}
        ");

        $tagStats = DB::selectOne("
            SELECT
                SUM(pt.quantity) as total_tags
            FROM {$table} p
            JOIN photo_tags pt ON p.id = pt.photo_id
        ");

        return [
            'total_photos' => (int)$photoStats->photos,
            'total_tags' => (int)$tagStats->total_tags,
            'total_objects' => (int)$photoStats->total_objects,
            'total_brands' => (int)$photoStats->total_brands,
            'total_users' => (int)$photoStats->users,
            'total_teams' => (int)$photoStats->teams,
            'picked_up' => (int)$photoStats->picked_up,
            'not_picked_up' => (int)$photoStats->not_picked_up,
        ];
    }
}
