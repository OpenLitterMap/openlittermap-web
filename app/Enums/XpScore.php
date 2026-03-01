<?php

namespace App\Enums;

enum XpScore
{
    case Upload;
    case Object;
    case CustomTag;
    case Material;
    case Brand;
    case PickedUp;
    case Small;
    case Medium;
    case Large;
    case BagsLitter;

    /**
     * Get the integer XP value for this enum instance.
     */
    public function xp(): int
    {
        return match ($this) {
            self::Upload     => 5,
            self::Object     => 1,
            self::CustomTag  => 1,
            self::Material   => 2,
            self::Brand      => 3,
            self::PickedUp   => 5,
            self::Small      => 10,
            self::Medium     => 25,
            self::Large      => 50,
            self::BagsLitter => 10,
        };
    }

    /**
     * Map a tag-type string to its XP value.
     */
    public static function getTagXp(string $type): int
    {
        return match ($type) {
            'upload'     => self::Upload->xp(),
            'object'     => self::Object->xp(),
            'custom_tag' => self::CustomTag->xp(),
            'material'   => self::Material->xp(),
            'brand'      => self::Brand->xp(),
            'picked_up'  => self::PickedUp->xp(),
            default      => self::Object->xp(),
        };
    }

    /**
     * Map an object-key string to its XP value (with overrides).
     */
    public static function getObjectXp(string $key): int
    {
        return match ($key) {
            'dumping_small'  => self::Small->xp(),
            'dumping_medium' => self::Medium->xp(),
            'dumping_large'  => self::Large->xp(),
            'bags_litter'    => self::BagsLitter->xp(),
            default          => self::Object->xp(),
        };
    }
}
