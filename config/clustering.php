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
    | Dynamic Grid Configuration
    |--------------------------------------------------------------------------
    |
    | Base grid size in degrees for zoom level 0
    | Grid size halves every 2 zoom levels: 90°, 45°, 22.5°, 11.25°...
    |
    */
    'base_grid_deg' => 90.0,

    /*
    |--------------------------------------------------------------------------
    | Global Tile Key
    |--------------------------------------------------------------------------
    |
    | Sentinel value for global clustering (must fit in UNSIGNED INT)
    |
    */
    'global_tile_key' => 4294967295,

    /*
    |--------------------------------------------------------------------------
    | Zoom Levels
    |--------------------------------------------------------------------------
    |
    | Available zoom levels for clustering
    | - global_zooms: Processed using single global query
    | - tile_zooms: Processed per-tile (or batch)
    |
    */
    'zoom_levels' => [
        'all' => [0, 2, 4, 6, 8, 10, 12, 14, 16],
        'global' => [0, 2, 4, 6],
        'tile' => [8, 10, 12, 14, 16],
    ],

    /*
    |--------------------------------------------------------------------------
    | Clustering Thresholds
    |--------------------------------------------------------------------------
    */

    // Minimum number of photos required to form a cluster
    // Set to 1 to show every photo as a cluster
    'min_cluster_size' => env('CLUSTERING_MIN_SIZE', 1),

    /*
    |--------------------------------------------------------------------------
    | Grid Sizes Per Zoom Level
    |--------------------------------------------------------------------------
    |
    | Define custom grid sizes for each zoom level.
    | Smaller values = more clusters (finer detail)
    | For tile zooms (8-16), these must divide evenly into 0.05
    |
    */
    'grid_sizes' => [
        // Global zooms - can be any value
        0 => 30.0,      // ~3330km cells - ~5-10 world clusters
        2 => 15.0,      // ~1665km cells - ~20-30 clusters
        4 => 5.0,       // ~555km cells - ~100-150 clusters
        6 => 2.0,       // ~222km cells - ~300-500 clusters

        // Tile zooms - must result in clean integer factors with 0.05
        8 => 0.8,       // factor 16 (0.8/0.05=16) - ~89km cells
        10 => 0.4,      // factor 8 (0.4/0.05=8) - ~44km cells
        12 => 0.1,      // factor 2 (0.1/0.05=2) - ~11km cells - many more clusters
        14 => 0.05,     // factor 1 (0.05/0.05=1) - ~5.5km cells - maximum clusters
        16 => 0.05,     // factor 1 (0.05/0.05=1) - ~5.5km cells - maximum clusters
    ],

    /*
    |--------------------------------------------------------------------------
    | Smallest Grid Size
    |--------------------------------------------------------------------------
    |
    | The smallest grid size used (for zoom 16). This determines the
    | precision of generated cell columns.
    |
    */
    'smallest_grid' => 0.05,  // Must match the generated columns in photos table

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
    'update_chunk_size' => env('CLUSTERING_UPDATE_CHUNK', 50000),

    // Whether to use spatial indexes for queries
    'use_spatial_index' => env('CLUSTERING_USE_SPATIAL', true),

    // Maximum clusters to return in API response
    'max_clusters_per_request' => env('CLUSTERING_MAX_RESPONSE', 5000),
];
