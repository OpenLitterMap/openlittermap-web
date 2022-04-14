<?php

namespace App\Models\Litter\Categories;

use App\Models\Litter\LitterCategory;

class Brand extends LitterCategory
{
    public static function types(): array
    {
        return [
            'aadrink',
            'acadia',
            'adidas',
            'albertheijn',
            'aldi',
            'amazon',
            'amstel',
            'anheuser_busch',
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
            'calanda',
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
            'modelo',
            'molson_coors',
            'monster',

            'nero',
            'nescafe',
            'nestle',
            'nike',

            'obriens',
            'ok_',

            'pepsi',
            'powerade',

            'redbull',
            'ribena',

            'samsung',
            'sainsburys',
            'schutters',
            'seven_eleven',
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
            'tim_hortons',
            'thins',

            'volvic',

            'waitrose',
            'walkers',
            'wendys',
            'woolworths',
            'wilde_and_greene',
            'winston',
            'wrigleys',
        ];
    }
}
