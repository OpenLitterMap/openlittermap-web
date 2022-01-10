<?php

namespace App\Models\Litter\Categories;

use App\Models\Litter\LitterCategory;

class Brand extends LitterCategory
{
    public static function types(): array
    {
        return [
            'aadrink',
            'adidas',
            'albertheijn',
            'aldi',
            'amazon',
            'amstel',
            'apple',
            'applegreen',
            'asahi',
            'avoca',

            'bacardi',
            'ballygowan',
            'bewleys',
            'brambles',
            'budweiser',
            'bulmers',
            'bullit',
            'burgerking',
            'butlers',

            'cadburys',
            'cafe_nero',
            'camel',
            'caprisun',
            'carlsberg',
            'centra',
            'coke',
            'circlek',
            'coles',
            'colgate',
            'corona',
            'costa',

            'doritos',
            'drpepper',
            'dunnes',
            'duracell',
            'durex',

            'evian',
            'esquires',

            'fanta',
            'fernandes',
            'fosters',
            'frank_and_honest',
            'fritolay',

            'gatorade',
            'gillette',
            'goldenpower',
            'guinness',

            'haribo',
            'heineken',
            'hertog_jan',

            'insomnia',

            'kellogs',
            'kfc',

            'lavish',
            'lego',
            'lidl',
            'lindenvillage',
            'lipton',
            'lolly_and_cookes',
            'loreal',
            'lucozade',

            'marlboro',
            'mars',
            'mcdonalds',
            'monster',

            'nero',
            'nescafe',
            'nestle',
            'nike',

            'obriens',

            'pepsi',
            'powerade',

            'redbull',
            'ribena',

            'samsung',
            'sainsburys',
            'schutters',
            'slammers',
            'spa',
            'spar',
            'stella',
            'subway',
            'supermacs',
            'supervalu',
            'starbucks',

            'tayto',
            'tesco',
            'thins',

            'volvic',

            'waitrose',
            'walkers',
            'woolworths',
            'wilde_and_greene',
            'wrigleys',
        ];
    }
}
