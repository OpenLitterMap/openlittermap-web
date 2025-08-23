<?php

namespace App\Services\Points\Builders;

class QueryBuilder
{
    /**
     * Build optimized WHERE clause with bindings
     */
    public function buildWhere(array $params): array
    {
        $conditions = ['verified >= 2'];
        $bindings = [];

        // Date filters
        if (!empty($params['year'])) {
            $conditions[] = 'YEAR(datetime) = ?';
            $bindings[] = $params['year'];
        } elseif (!empty($params['from']) && !empty($params['to'])) {
            $conditions[] = 'DATE(datetime) BETWEEN ? AND ?';
            $bindings[] = $params['from'];
            $bindings[] = $params['to'];
        }

        // User filter
        if (!empty($params['username'])) {
            $conditions[] = 'user_id IN (SELECT id FROM users WHERE username = ?)';
            $bindings[] = $params['username'];
        }

        // Geographic filters
        foreach (['country_id', 'state_id', 'city_id'] as $field) {
            if (!empty($params[$field])) {
                $conditions[] = "{$field} = ?";
                $bindings[] = $params[$field];
            }
        }

        // Bounding box
        if (!empty($params['bbox'])) {
            $this->addBboxCondition($params['bbox'], $conditions, $bindings);
        }

        // Category filters
        if (!empty($params['categories'])) {
            $placeholders = str_repeat('?,', count($params['categories']) - 1) . '?';
            $conditions[] = "id IN (
                SELECT DISTINCT photo_id
                FROM photo_tags pt
                JOIN categories c ON pt.category_id = c.id
                WHERE c.key IN ({$placeholders})
            )";
            $bindings = array_merge($bindings, $params['categories']);
        }

        // Object filters
        if (!empty($params['litter_objects'])) {
            $placeholders = str_repeat('?,', count($params['litter_objects']) - 1) . '?';
            $conditions[] = "id IN (
                SELECT DISTINCT photo_id
                FROM photo_tags pt
                JOIN litter_objects lo ON pt.litter_object_id = lo.id
                WHERE lo.key IN ({$placeholders})
            )";
            $bindings = array_merge($bindings, $params['litter_objects']);
        }

        return [implode(' AND ', $conditions), $bindings];
    }

    /**
     * Add bounding box condition
     */
    private function addBboxCondition($bbox, array &$conditions, array &$bindings): void
    {
        if (is_array($bbox)) {
            if (isset($bbox['left'], $bbox['bottom'], $bbox['right'], $bbox['top'])) {
                $conditions[] = 'lat BETWEEN ? AND ? AND lon BETWEEN ? AND ?';
                array_push($bindings, $bbox['bottom'], $bbox['top'], $bbox['left'], $bbox['right']);
            }
        } elseif (is_string($bbox)) {
            $parts = explode(',', $bbox);
            if (count($parts) === 4) {
                $conditions[] = 'lat BETWEEN ? AND ? AND lon BETWEEN ? AND ?';
                array_push($bindings, $parts[1], $parts[3], $parts[0], $parts[2]);
            }
        }
    }
}
