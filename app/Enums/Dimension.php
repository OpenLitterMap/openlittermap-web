<?php

namespace App\Enums;

enum Dimension: string
{
    case LITTER_OBJECT = 'object';
    case CATEGORY = 'category';
    case MATERIAL = 'material';
    case BRAND = 'brand';
    case CUSTOM_TAG = 'custom_tag';
    case TYPE = 'type';

    public function table(): string
    {
        return match($this) {
            self::LITTER_OBJECT => 'litter_objects',
            self::CATEGORY => 'categories',
            self::MATERIAL => 'materials',
            self::BRAND => 'brandslist',
            self::CUSTOM_TAG => 'custom_tags_new',
            self::TYPE => 'litter_object_types',
        };
    }

    public static function fromTable(string $table): ?self
    {
        return match($table) {
            'litter_objects' => self::LITTER_OBJECT,
            'categories' => self::CATEGORY,
            'materials' => self::MATERIAL,
            'brandslist' => self::BRAND,
            'custom_tags_new', 'custom_tags' => self::CUSTOM_TAG,
            'litter_object_types' => self::TYPE,
            default => null,
        };
    }
}
