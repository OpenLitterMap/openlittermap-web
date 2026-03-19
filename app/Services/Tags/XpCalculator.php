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
        $xp = 0; // Tag XP only — upload XP is awarded separately by UploadPhotoController

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
     * Calculate XP from photo summary structure.
     * Supports both flat array (v5.1) and nested dict (v5.0) formats.
     */
    public static function calculateFromSummary(array $summary): int
    {
        $tags = $summary['tags'] ?? [];
        $objectKeys = $summary['keys']['objects'] ?? [];

        // Detect format: flat array (list) vs nested dict (associative)
        if (array_is_list($tags)) {
            return self::calculateFromFlatSummary($tags, $objectKeys);
        }

        return self::calculateFromNestedSummary($tags, $objectKeys);
    }

    /**
     * Calculate XP from flat array summary format (v5.1).
     */
    private static function calculateFromFlatSummary(array $tags, array $objectKeys): int
    {
        $xp = 0;

        foreach ($tags as $tag) {
            $quantity = $tag['quantity'] ?? 0;
            $objectId = $tag['object_id'] ?? 0;

            // Only award object XP if there's an actual object
            if ($objectId > 0) {
                $objectKey = $objectKeys[$objectId] ?? null;
                if ($objectKey) {
                    $xp += $quantity * XpScore::getObjectXp($objectKey);
                } else {
                    $xp += $quantity * XpScore::Object->xp();
                }
            }

            // Materials: set membership, weighted by parent qty → each material contributes qty * Material XP
            foreach ($tag['materials'] ?? [] as $materialId) {
                $xp += $quantity * XpScore::Material->xp();
            }

            // Brands: independent quantities
            foreach ((array) ($tag['brands'] ?? []) as $brandId => $brandQty) {
                $xp += $brandQty * XpScore::Brand->xp();
            }

            // Custom tags: set membership, weighted by parent qty
            foreach ($tag['custom_tags'] ?? [] as $customId) {
                $xp += $quantity * XpScore::CustomTag->xp();
            }
        }

        return $xp;
    }

    /**
     * Calculate XP from nested dict summary format (v5.0 legacy).
     */
    private static function calculateFromNestedSummary(array $tags, array $objectKeys): int
    {
        $xp = 0;

        foreach ($tags as $categoryId => $objects) {
            foreach ($objects as $objectId => $data) {
                $quantity = $data['quantity'] ?? 0;

                $objectKey = $objectKeys[$objectId] ?? null;
                if ($objectKey) {
                    $xp += $quantity * XpScore::getObjectXp($objectKey);
                } else {
                    $xp += $quantity * XpScore::Object->xp();
                }

                foreach ($data['materials'] ?? [] as $materialId => $qty) {
                    $xp += $qty * XpScore::Material->xp();
                }

                foreach ($data['brands'] ?? [] as $brandId => $qty) {
                    $xp += $qty * XpScore::Brand->xp();
                }

                foreach ($data['custom_tags'] ?? [] as $customId => $qty) {
                    $xp += $qty * XpScore::CustomTag->xp();
                }
            }
        }

        return $xp;
    }

    /**
     * Deprecated?
     * Calculate XP for a single object by key
     * Used during summary generation when we have the key
     */
    public static function getObjectXp(string $objectKey, ?string $typeKey = null): int
    {
        return XpScore::getObjectXp($objectKey, $typeKey);
    }

    /**
     * Deprecated?
     * Calculate XP for a tag type
     */
    public static function getTagXp(string $tagType): int
    {
        return XpScore::getTagXp($tagType);
    }
}
