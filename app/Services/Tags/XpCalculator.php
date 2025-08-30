<?php

declare(strict_types=1);

namespace App\Services\Tags;

use App\Enums\XpScore;

/**
 * Centralized XP calculation to ensure consistency across services
 */
final class XpCalculator
{
    /**
     * Calculate XP for a photo based on its tags with proper enum values
     *
     * @param array $tags Array with structure:
     *   [
     *     'objects' => [id => quantity, ...],
     *     'brands' => [id => quantity, ...],
     *     'materials' => [id => quantity, ...],
     *   ]
     * @param array|null $objectKeys Optional mapping of object IDs to keys for special XP calculation
     *   [id => key, ...]
     */
    public static function calculateFromTags(array $tags, ?array $objectKeys = null): int
    {
        $xp = XpScore::Upload->xp(); // Base XP: 5

        // Objects: Check for special objects, otherwise use standard XP
        foreach ($tags['objects'] ?? [] as $id => $quantity) {
            // If we have the object key mapping, check for special XP values
            if ($objectKeys && isset($objectKeys[$id])) {
                $xp += $quantity * XpScore::getObjectXp($objectKeys[$id]);
            } else {
                // Default object XP: 1
                $xp += $quantity * XpScore::Object->xp();
            }
        }

        // Brands: 3 XP each
        foreach ($tags['brands'] ?? [] as $id => $quantity) {
            $xp += $quantity * XpScore::Brand->xp();
        }

        // Materials: 2 XP each
        foreach ($tags['materials'] ?? [] as $id => $quantity) {
            $xp += $quantity * XpScore::Material->xp();
        }

        // Custom tags: 1 XP each
        foreach ($tags['custom_tags'] ?? [] as $id => $quantity) {
            $xp += $quantity * XpScore::CustomTag->xp();
        }

        return $xp;
    }

    /**
     * Calculate XP from photo summary structure
     * This version can access the keys mapping from the summary
     */
    public static function calculateFromSummary(array $summary): int
    {
        $xp = XpScore::Upload->xp(); // Base XP: 5

        $objectKeys = $summary['keys']['objects'] ?? [];

        foreach ($summary['tags'] ?? [] as $categoryId => $objects) {
            foreach ($objects as $objectId => $data) {
                $quantity = $data['quantity'] ?? 0;

                // Check if this is a special object
                $objectKey = $objectKeys[$objectId] ?? null;
                if ($objectKey) {
                    $xp += $quantity * XpScore::getObjectXp($objectKey);
                } else {
                    $xp += $quantity * XpScore::Object->xp();
                }

                // Materials: 2 XP each
                foreach ($data['materials'] ?? [] as $materialId => $qty) {
                    $xp += $qty * XpScore::Material->xp();
                }

                // Brands: 3 XP each
                foreach ($data['brands'] ?? [] as $brandId => $qty) {
                    $xp += $qty * XpScore::Brand->xp();
                }

                // Custom tags: 1 XP each
                foreach ($data['custom_tags'] ?? [] as $customId => $qty) {
                    $xp += $qty * XpScore::CustomTag->xp();
                }
            }
        }

        return $xp;
    }

    /**
     * Calculate XP for a single object by key
     * Used during summary generation when we have the key
     */
    public static function getObjectXp(string $objectKey): int
    {
        return XpScore::getObjectXp($objectKey);
    }

    /**
     * Calculate XP for a tag type
     */
    public static function getTagXp(string $tagType): int
    {
        return XpScore::getTagXp($tagType);
    }
}
