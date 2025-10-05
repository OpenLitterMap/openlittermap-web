<?php

namespace App\Services\Points\Builders;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class QueryBuilder
{
    /**
     * Build query with all filters applied
     */
    public function build(array $params): Builder
    {
        $query = DB::table('photos')->where('verified', '>=', 2);

        $this->applyDateFilters($query, $params);
        $this->applyUserFilter($query, $params);
        $this->applyLocationFilters($query, $params);
        $this->applySpatialFilter($query, $params);
        $this->applyTagFilters($query, $params);

        return $query;
    }

    /**
     * Apply date filters
     */
    private function applyDateFilters($query, array $params): void
    {
        if (!empty($params['year'])) {
            $query->whereYear('datetime', $params['year']);
        } elseif (!empty($params['from']) && !empty($params['to'])) {
            $query->whereBetween('datetime', [$params['from'], $params['to']]);
        }
    }

    /**
     * Apply user filter with visibility check
     */
    private function applyUserFilter($query, array $params): void
    {
        if (!empty($params['username'])) {
            $query->whereExists(function($q) use ($params) {
                $q->select(DB::raw(1))
                    ->from('users')
                    ->whereColumn('users.id', 'photos.user_id')
                    ->where('username', $params['username'])
                    ->where('show_username_maps', true);
            });
        }
    }

    /**
     * Apply location filters
     */
    private function applyLocationFilters($query, array $params): void
    {
        foreach (['country_id', 'state_id', 'city_id'] as $field) {
            if (!empty($params[$field])) {
                $query->where($field, $params[$field]);
            }
        }
    }

    /**
     * Apply spatial bounding box filter
     */
    private function applySpatialFilter($query, array $params): void
    {
        if (!empty($params['bbox'])) {
            $bbox = $params['bbox'];
            if (is_array($bbox) && isset($bbox['left'], $bbox['bottom'], $bbox['right'], $bbox['top'])) {
                $query->whereBetween('lat', [$bbox['bottom'], $bbox['top']])
                    ->whereBetween('lon', [$bbox['left'], $bbox['right']]);
            }
        }
    }

    /**
     * Apply tag filters (categories and objects)
     * FIXED: Use if-elseif to avoid applying multiple conflicting whereExists clauses
     */
    private function applyTagFilters($query, array $params): void
    {
        $hasCategories = !empty($params['categories']);
        $hasObjects = !empty($params['litter_objects']);

        // When both filters are present, they must match the same tag
        if ($hasCategories && $hasObjects) {
            $query->whereExists(function($q) use ($params) {
                $q->select(DB::raw(1))
                    ->from('photo_tags')
                    ->join('categories', 'photo_tags.category_id', '=', 'categories.id')
                    ->join('litter_objects', 'photo_tags.litter_object_id', '=', 'litter_objects.id')
                    ->whereColumn('photo_tags.photo_id', 'photos.id')
                    ->whereIn('categories.key', $params['categories'])
                    ->whereIn('litter_objects.key', $params['litter_objects']);
            });
        }
        // Only category filter
        elseif ($hasCategories) {
            $query->whereExists(function($q) use ($params) {
                $q->select(DB::raw(1))
                    ->from('photo_tags')
                    ->join('categories', 'photo_tags.category_id', '=', 'categories.id')
                    ->whereColumn('photo_tags.photo_id', 'photos.id')
                    ->whereIn('categories.key', $params['categories']);
            });
        }
        // Only litter object filter
        elseif ($hasObjects) {
            $query->whereExists(function($q) use ($params) {
                $q->select(DB::raw(1))
                    ->from('photo_tags')
                    ->join('litter_objects', 'photo_tags.litter_object_id', '=', 'litter_objects.id')
                    ->whereColumn('photo_tags.photo_id', 'photos.id')
                    ->whereIn('litter_objects.key', $params['litter_objects']);
            });
        }
    }
}
