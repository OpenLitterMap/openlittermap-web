<?php

namespace App\Enums;

/**
 * Photo verification status.
 *
 * Progression: uploaded → tagged → verified → bbox → AI-ready
 */
enum VerificationStatus: int
{
    case UNVERIFIED = 0;
    case VERIFIED = 1;        // Crowd-verified (future: AI-verified)
    case ADMIN_APPROVED = 2;  // Manually verified by admin or trusted user
    case BBOX_APPLIED = 3;    // Bounding boxes drawn
    case BBOX_VERIFIED = 4;   // Bounding boxes verified by second user
    case AI_READY = 5;        // 100% correct, ready for OpenLitterAI training

    public function label(): string
    {
        return match ($this) {
            self::UNVERIFIED => 'Unverified',
            self::VERIFIED => 'Verified',
            self::ADMIN_APPROVED => 'Admin Approved',
            self::BBOX_APPLIED => 'BBox Applied',
            self::BBOX_VERIFIED => 'BBox Verified',
            self::AI_READY => 'AI Ready',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::UNVERIFIED => 'slate',
            self::VERIFIED => 'blue',
            self::ADMIN_APPROVED => 'green',
            self::BBOX_APPLIED => 'amber',
            self::BBOX_VERIFIED => 'purple',
            self::AI_READY => 'emerald',
        };
    }

    /**
     * Is this photo's data considered reliable enough for public display?
     */
    public function isPublicReady(): bool
    {
        return $this->value >= self::ADMIN_APPROVED->value;
    }

    /**
     * Is this photo eligible for OpenLitterAI training data?
     */
    public function isAiReady(): bool
    {
        return $this === self::AI_READY;
    }

    /**
     * Has this photo been verified at all (by anyone or any method)?
     */
    public function isVerified(): bool
    {
        return $this->value >= self::VERIFIED->value;
    }
}
