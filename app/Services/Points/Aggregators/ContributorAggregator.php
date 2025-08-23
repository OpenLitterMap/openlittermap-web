<?php

namespace App\Services\Points\Aggregators;

use Illuminate\Support\Facades\DB;

class ContributorAggregator
{
    /**
     * Aggregate top contributors
     */
    public function aggregate(string $whereSql, array $bindings): array
    {
        $results = DB::select("
        SELECT
            u.username,
            u.name,
            COUNT(DISTINCT p.id)         AS photo_count,
            SUM(p.total_litter)          AS total_litter,
            MIN(p.datetime)              AS first_contribution,
            MAX(p.datetime)              AS last_contribution
        FROM (
            SELECT id, user_id, total_litter, datetime
            FROM photos
            WHERE {$whereSql}
        ) p
        JOIN users u ON u.id = p.user_id
        WHERE u.show_username_maps = 1
        GROUP BY u.id, u.username, u.name
        ORDER BY total_litter DESC
        LIMIT 10
    ", $bindings);

        return array_map(fn($user) => [
            'username'            => $user->username,
            'name'                => $user->name,
            'photo_count'         => (int) $user->photo_count,
            'total_litter'        => (int) $user->total_litter,
            'first_contribution'  => $user->first_contribution,
            'last_contribution'   => $user->last_contribution,
            'avatar'              => null,
        ], $results);
    }

    /**
     * Aggregate from temporary table
     */
    public function aggregateFromTable(string $table): array
    {
        $results = DB::select("
            SELECT
                u.username,
                u.name,
                COUNT(DISTINCT p.id) as photo_count,
                SUM(p.total_litter) as total_litter,
                MIN(p.datetime) as first_contribution,
                MAX(p.datetime) as last_contribution
            FROM {$table} p
            JOIN users u ON u.id = p.user_id
            WHERE u.show_username_maps = 1
            GROUP BY u.id, u.username, u.name
            ORDER BY total_litter DESC
            LIMIT 10
        ");

        return array_map(fn($user) => [
            'username' => $user->username,
            'name' => $user->name,
            'photo_count' => (int)$user->photo_count,
            'total_litter' => (int)$user->total_litter,
            'first_contribution' => $user->first_contribution,
            'last_contribution' => $user->last_contribution,
            'avatar' => null,
        ], $results);
    }
}
