<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tile Configuration
    |--------------------------------------------------------------------------
    |
    | The size of each tile in degrees. This creates a grid across the world
    | where each tile is 0.25° x 0.25° (approximately 28km x 28km at equator)
    |
    */
    'tile_size' => env('CLUSTERING_TILE_SIZE', 0.25),

    /*
    |--------------------------------------------------------------------------
    | Zoom Level Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for each zoom level:
    | - grid: The size of clustering grid cells in degrees
    | - min_points: Minimum photos needed to create a cluster
    |
    */
    'zoom_levels' => [
        0  => ['grid' => 8.0,   'min_points' => 10],
        2  => ['grid' =>  8.0,   'min_points' =>  8],
        4  => ['grid' =>  4.0,   'min_points' =>  6],
        6  => ['grid' =>  2.0,   'min_points' =>  5],
        8  => ['grid' =>  1.0,   'min_points' =>  3],
        10  => ['grid' =>  0.5,   'min_points' =>  3],
        12  => ['grid' =>  0.25,  'min_points' =>  2],
        14  => ['grid' =>  0.10,  'min_points' =>  1],
        16  => ['grid' =>  0.05,  'min_points' =>  1],
    ],

    /*
    |--------------------------------------------------------------------------
    | Processing Configuration
    |--------------------------------------------------------------------------
    */

    // How long to keep dirty tiles before automatic cleanup (hours)
    'dirty_tile_ttl' => env('CLUSTERING_DIRTY_TTL', 24),

    // Cache TTL for API responses (seconds)
    'cache_ttl' => env('CLUSTERING_CACHE_TTL', 300),

    /*
    |--------------------------------------------------------------------------
    | Performance Tuning
    |--------------------------------------------------------------------------
    */

    // Maximum number of photos to update tile keys per chunk
    'update_chunk_size' => env('CLUSTERING_UPDATE_CHUNK', 1000),

    // Whether to use spatial indexes for queries
    'use_spatial_index' => env('CLUSTERING_USE_SPATIAL', true),

    // Maximum clusters to return in API response
    'max_clusters_per_request' => env('CLUSTERING_MAX_RESPONSE', 5000),
];
