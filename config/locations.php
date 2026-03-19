<?php

return [

    /**
     * Cache TTL settings (in seconds)
     */
    'cache' => [
        'ttl_short' => env('LOCATION_CACHE_SHORT', 300),      // 5 minutes
        'ttl_medium' => env('LOCATION_CACHE_MEDIUM', 1800),   // 30 minutes
        'ttl_long' => env('LOCATION_CACHE_LONG', 3600),       // 1 hour
    ],

    /**
     * Pagination settings
     */
    'pagination' => [
        'max_per_page' => env('LOCATION_MAX_PER_PAGE', 100),
        'default_per_page' => env('LOCATION_DEFAULT_PER_PAGE', 50),
    ],

    /**
     * Redis settings
     */
    'redis' => [
        'max_ranking_items' => env('REDIS_MAX_RANKING_ITEMS', 500),
        'chunk_size' => env('REDIS_CHUNK_SIZE', 500),
        'time_series_ttl_ms' => env('REDIS_TIME_SERIES_TTL_MS', 63072000000), // 2 years
        'monthly_ranking_ttl' => env('REDIS_MONTHLY_RANKING_TTL', 15552000),  // 180 days
    ],

    /**
     * Processing limits
     */
    'processing' => [
        'max_batch_size' => env('PHOTO_MAX_BATCH_SIZE', 1000),
        'max_items_per_photo' => env('PHOTO_MAX_ITEMS', 1000),
        'max_quantity_per_item' => env('PHOTO_MAX_QUANTITY', 100),
    ],

    /**
     * Allowed sort columns for database queries
     */
    'allowed_sort_columns' => [
        'id',
        'country',
        'state',
        'city',
        'created_at',
        'updated_at',
        'manual_verify',
        'created_by',
        'user_id_last_uploaded',
    ],

    /**
     * Global level thresholds
     */
    'levels' => [
        0 => ['min' => 0, 'max' => 1000],
        1 => ['min' => 1000, 'max' => 10000],
        2 => ['min' => 10000, 'max' => 100000],
        3 => ['min' => 100000, 'max' => 250000],
        4 => ['min' => 250000, 'max' => 500000],
        5 => ['min' => 500000, 'max' => 1000000],
        6 => ['min' => 1000000, 'max' => 2500000],
        7 => ['min' => 2500000, 'max' => 5000000],
        8 => ['min' => 5000000, 'max' => 10000000],
        9 => ['min' => 10000000, 'max' => PHP_INT_MAX],
    ],

    /**
     * Feature flags for v1 rollout
     */
    'features' => [
        'timeseries' => env('FEATURE_TIMESERIES', false),
        'leaderboard' => env('FEATURE_LEADERBOARD', false),
        'summary' => env('FEATURE_SUMMARY', false),
        'recent_activity' => env('FEATURE_RECENT_ACTIVITY', false),
    ],
];
