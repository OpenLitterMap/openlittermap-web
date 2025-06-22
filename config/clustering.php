<?php

// config/clustering.php

return [
    /*
    |--------------------------------------------------------------------------
    | Clustering Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the OpenLitterMap clustering system that groups
    | photos into spatial clusters at multiple zoom levels.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Pixel Radius
    |--------------------------------------------------------------------------
    |
    | Cluster radius in pixels. This determines how close photos need to be
    | to be grouped together. Default 80 matches Supercluster's default.
    | Smaller values = more, tighter clusters. Larger = fewer, spread out.
    |
    */
    'pixel_radius' => env('CLUSTERING_PIXEL_RADIUS', 80),

    /*
    |--------------------------------------------------------------------------
    | Zoom Levels
    |--------------------------------------------------------------------------
    |
    | The range of zoom levels to generate clusters for.
    | Min: 2 (whole world view)
    | Max: 16 (street level detail)
    |
    */
    'min_zoom' => env('CLUSTERING_MIN_ZOOM', 2),
    'max_zoom' => env('CLUSTERING_MAX_ZOOM', 16),

    /*
    |--------------------------------------------------------------------------
    | Singleton Policy
    |--------------------------------------------------------------------------
    |
    | How to handle single-photo clusters:
    | - 'none': Never create single-photo clusters
    | - 'max_zoom_only': Only at the highest zoom level (current behavior)
    | - 'all': Allow single-photo clusters at all zoom levels
    |
    */
    'singleton_policy' => env('CLUSTERING_SINGLETON_POLICY', 'max_zoom_only'),

    /*
    |--------------------------------------------------------------------------
    | Advisory Lock Timeout
    |--------------------------------------------------------------------------
    |
    | How long to wait (in seconds) when trying to acquire a lock on a tile.
    | Prevents multiple workers from processing the same tile simultaneously.
    |
    */
    'lock_timeout' => env('CLUSTERING_LOCK_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Debug Mode
    |--------------------------------------------------------------------------
    |
    | Enable additional logging for troubleshooting clustering operations.
    |
    */
    'debug' => env('CLUSTERING_DEBUG', false),
];
