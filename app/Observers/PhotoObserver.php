<?php

namespace App\Observers;

use App\Models\Photo;
use App\Services\Clustering\ClusteringService;

class PhotoObserver
{
    private ClusteringService $clustering;

    public function __construct(ClusteringService $clustering)
    {
        $this->clustering = $clustering;
    }

    /**
     * Handle the Photo "saving" event.
     * Update tile_key BEFORE save if coordinates changed or photo becomes verified
     */
    public function saving(Photo $photo): void
    {
        // Skip unverified photos entirely
        if ($photo->verified != 2) {
            return;
        }

        // Check if coordinates are changing
        if ($photo->isDirty(['lat', 'lon'])) {
            // Compute new tile key
            $newTileKey = $this->clustering->computeTileKey(
                $photo->lat,
                $photo->lon
            );

            // Get old tile key before it changes
            $oldTileKey = $photo->getOriginal('tile_key');

            // Mark old tile as dirty if it exists and is different
            if ($oldTileKey && $oldTileKey != $newTileKey) {
                $this->clustering->markTileDirty($oldTileKey);
            }

            // Update the tile key
            $photo->tile_key = $newTileKey;
        }

        // Handle case where photo becomes verified but coordinates haven't changed
        if ($photo->isDirty('verified') && $photo->verified == 2 && !$photo->tile_key) {
            $photo->tile_key = $this->clustering->computeTileKey($photo->lat, $photo->lon);
        }
    }

    /**
     * Handle the Photo "saved" event.
     * Mark new tile as dirty after save
     */
    public function saved(Photo $photo): void
    {
        // Skip unverified photos entirely
        if ($photo->verified != 2) {
            return;
        }

        // Only if has a tile key
        if ($photo->tile_key) {
            // If coordinates or verified status changed, mark dirty
            if ($photo->wasChanged(['lat', 'lon', 'verified', 'tile_key'])) {
                $this->clustering->markTileDirty($photo->tile_key);
            }
        }
    }

    /**
     * Handle the Photo "deleting" event.
     * Mark tile dirty before delete
     */
    public function deleting(Photo $photo): void
    {
        if ($photo->verified == 2 && $photo->tile_key) {
            $this->clustering->markTileDirty($photo->tile_key);
        }
    }
}
