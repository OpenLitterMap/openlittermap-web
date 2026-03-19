<?php

namespace App\Enums;

use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\Materials;
use App\Models\Litter\Tags\CustomTagNew;

enum LitterModels: string
{
    // Material is the old model. Materials is the new one.
    case MATERIALS = "material";

    // CustomTag is the old model. CustomTagNew is the new one.
    case CUSTOM_TAGS = "custom_tag";

    // Brand is the old model. BrandList is the new one.
    case BRANDS = "brand";

    public function modelClass(): string
    {
        return match($this) {
            self::MATERIALS => Materials::class,
            self::CUSTOM_TAGS => CustomTagNew::class,
            self::BRANDS => BrandList::class,
        };
    }
}
