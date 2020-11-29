<?php

namespace App\Models\Litter\Categories;

use App\Models\Litter\LitterCategory;

class Brand extends LitterCategory
{
    protected $fillable = [
        'photo_id',

    	'adidas',
    	'amazon',
        'aldi',
    	'apple',
        'applegreen',
        'asahi',
        'avoca',

        'ballygowan',
        'bewleys',
        'brambles',
    	'budweiser',
        'bulmers',
        'burgerking',
        'butlers',

        'cadburys',
        'cafe_nero',
        'camel',
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

        'esquires',

        'frank_and_honest',
    	'fritolay',

        'gatorade',
    	'gillette',
        'guinness',

        'haribo',
    	'heineken',

        'insomnia',

    	'kellogs',
        'kfc',

    	'lego',
        'lidl',
        'lindenvillage',
        'lolly_and_cookes',
    	'loreal',
        'lucozade',

    	'marlboro',
        'mars',
    	'mcdonalds',

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
        'wrigleys'
    ];

    /**
     * Pre-defined litter types available on this class
     */
    public function types ()
    {
        return [
            'adidas',
            'amazon',
            'aldi',
            'apple',
            'applegreen',
            'asahi',
            'avoca',

            'ballygowan',
            'bewleys',
            'brambles',
            'budweiser',
            'bulmers',
            'burgerking',
            'butlers',

            'cadburys',
            'cafe_nero',
            'camel',
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

            'esquires',

            'frank_and_honest',
            'fritolay',

            'gatorade',
            'gillette',
            'guinness',

            'haribo',
            'heineken',

            'insomnia',

            'kellogs',
            'kfc',

            'lego',
            'lidl',
            'lindenvillage',
            'lolly_and_cookes',
            'loreal',
            'lucozade',

            'marlboro',
            'mars',
            'mcdonalds',

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
            'wrigleys'
        ];
    }
}
