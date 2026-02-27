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
        $query = DB::table('photos')->where('verified', '>=', 2)->where('is_public', true);

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
     * Apply tag filters (categories, objects, materials, brands, custom tags)
     */
    private function applyTagFilters($query, array $params): void
    {
        $hasCategories = !empty($params['categories']);
        $hasObjects = !empty($params['litter_objects']);
        $hasMaterials = !empty($params['materials']);
        $hasBrands = !empty($params['brands']);
        $hasCustomTags = !empty($params['custom_tags']);

        if (!$hasCategories && !$hasObjects && !$hasMaterials && !$hasBrands && !$hasCustomTags) {
            return;
        }

        // Category/object filters via photo_tags
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
        } elseif ($hasCategories) {
            $query->whereExists(function($q) use ($params) {
                $q->select(DB::raw(1))
                    ->from('photo_tags')
                    ->join('categories', 'photo_tags.category_id', '=', 'categories.id')
                    ->whereColumn('photo_tags.photo_id', 'photos.id')
                    ->whereIn('categories.key', $params['categories']);
            });
        } elseif ($hasObjects) {
            $query->whereExists(function($q) use ($params) {
                $q->select(DB::raw(1))
                    ->from('photo_tags')
                    ->join('litter_objects', 'photo_tags.litter_object_id', '=', 'litter_objects.id')
                    ->whereColumn('photo_tags.photo_id', 'photos.id')
                    ->whereIn('litter_objects.key', $params['litter_objects']);
            });
        }

        // Material filter via photo_tag_extra_tags
        if ($hasMaterials) {
            $materialIds = $this->prefetchIds('materials', $params['materials']);
            if (!empty($materialIds)) {
                $query->whereExists(function($q) use ($materialIds) {
                    $q->select(DB::raw(1))
                        ->from('photo_tags')
                        ->join('photo_tag_extra_tags', 'photo_tags.id', '=', 'photo_tag_extra_tags.photo_tag_id')
                        ->whereColumn('photo_tags.photo_id', 'photos.id')
                        ->where('photo_tag_extra_tags.tag_type', 'material')
                        ->whereIn('photo_tag_extra_tags.tag_type_id', $materialIds);
                });
            }
        }

        // Brand filter via photo_tag_extra_tags
        if ($hasBrands) {
            $brandIds = $this->prefetchIds('brandslist', $params['brands']);
            if (!empty($brandIds)) {
                $query->whereExists(function($q) use ($brandIds) {
                    $q->select(DB::raw(1))
                        ->from('photo_tags')
                        ->join('photo_tag_extra_tags', 'photo_tags.id', '=', 'photo_tag_extra_tags.photo_tag_id')
                        ->whereColumn('photo_tags.photo_id', 'photos.id')
                        ->where('photo_tag_extra_tags.tag_type', 'brand')
                        ->whereIn('photo_tag_extra_tags.tag_type_id', $brandIds);
                });
            }
        }

        // Custom tag filter via photo_tag_extra_tags
        if ($hasCustomTags) {
            $query->whereExists(function($q) use ($params) {
                $q->select(DB::raw(1))
                    ->from('photo_tags')
                    ->join('photo_tag_extra_tags', 'photo_tags.id', '=', 'photo_tag_extra_tags.photo_tag_id')
                    ->join('custom_tags_new', 'photo_tag_extra_tags.tag_type_id', '=', 'custom_tags_new.id')
                    ->whereColumn('photo_tags.photo_id', 'photos.id')
                    ->where('photo_tag_extra_tags.tag_type', 'custom_tag')
                    ->whereIn('custom_tags_new.key', $params['custom_tags'])
                    ->where('custom_tags_new.approved', true);
            });
        }
    }

    /**
     * Prefetch IDs by key for a given table
     */
    private function prefetchIds(string $table, array $keys): array
    {
        if (empty($keys)) {
            return [];
        }

        return DB::table($table)
            ->whereIn('key', $keys)
            ->pluck('id')
            ->all();
    }
}
