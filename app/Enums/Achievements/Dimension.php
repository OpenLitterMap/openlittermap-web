<?php

namespace App\Enums\Achievements;

enum Dimension:string
{
    case UPLOADS  = 'uploads';
    case OBJECT   = 'object';
    case CATEGORY = 'category';
    case MATERIAL = 'material';
    case BRAND    = 'brand';
}
