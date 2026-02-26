<?php

namespace App\Observers;

use App\Enums\VerificationStatus;
use App\Models\Photo;
use App\Models\Teams\Team;
use App\Services\Clustering\ClusteringService;

class PhotoObserver
{
    private ClusteringService $clustering;

    public function __construct(ClusteringService $clustering)
    {
        $this->clustering = $clustering;
    }

    /**
     * When a photo is created, check if it belongs to a school team.
     * If so, mark it as private until the teacher approves.
     */
    public function creating(Photo $photo): void
    {
        if (! $photo->team_id) {
            return;
        }

        $team = Team::find($photo->team_id);

        if ($team && $team->isSchool()) {
            $photo->is_public = false;
        }
    }

    /**
     * Handle the Photo "saving" event.
     * Update tile_key BEFORE save if coordinates changed or photo becomes verified
     */
    public function saving(Photo $photo): void
    {
        // Skip photos below ADMIN_APPROVED (unverified, null, pending teacher approval)
        if (! $this->isPublicReady($photo)) {
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
        if ($photo->isDirty('verified') && $this->isPublicReady($photo) && !$photo->tile_key) {
            $photo->tile_key = $this->clustering->computeTileKey($photo->lat, $photo->lon);
        }
    }

    /**
     * Handle the Photo "saved" event.
     * Mark new tile as dirty after save, and mark team dirty if applicable
     */
    public function saved(Photo $photo): void
    {
        // Global tile dirty marking (verified >= ADMIN_APPROVED)
        if ($this->isPublicReady($photo) && $photo->tile_key) {
            if ($photo->wasChanged(['lat', 'lon', 'verified', 'tile_key'])) {
                $this->clustering->markTileDirty($photo->tile_key);
            }
        }

        // Team dirty marking (verified >= VERIFIED, i.e. at least tagged)
        $this->markTeamDirtyIfNeeded($photo);
    }

    /**
     * Handle the Photo "deleting" event.
     * Mark tile dirty before delete, and mark team dirty if applicable
     */
    public function deleting(Photo $photo): void
    {
        if ($this->isPublicReady($photo) && $photo->tile_key) {
            $this->clustering->markTileDirty($photo->tile_key);
        }

        if ($photo->team_id && $this->isTagged($photo)) {
            $this->clustering->markTeamDirty($photo->team_id);
        }
    }

    /**
     * Mark team as dirty if the photo is tagged and belongs to a team.
     * Handles team_id changes by marking both old and new teams.
     */
    private function markTeamDirtyIfNeeded(Photo $photo): void
    {
        if (! $this->isTagged($photo)) {
            return;
        }

        // If team_id changed, mark old team dirty too
        if ($photo->wasChanged('team_id')) {
            $oldTeamId = $photo->getOriginal('team_id');
            if ($oldTeamId) {
                $this->clustering->markTeamDirty($oldTeamId);
            }
        }

        if ($photo->team_id && $photo->wasChanged(['lat', 'lon', 'verified', 'team_id'])) {
            $this->clustering->markTeamDirty($photo->team_id);
        }
    }

    /**
     * Check if photo's verification status is ADMIN_APPROVED or higher.
     */
    private function isPublicReady(Photo $photo): bool
    {
        return $photo->verified !== null
            && $photo->verified->value >= VerificationStatus::ADMIN_APPROVED->value;
    }

    /**
     * Check if photo has been at least tagged (verified >= VERIFIED).
     * Team clustering includes tagged-but-unapproved photos.
     */
    private function isTagged(Photo $photo): bool
    {
        return $photo->verified !== null
            && $photo->verified->value >= VerificationStatus::VERIFIED->value;
    }
}
