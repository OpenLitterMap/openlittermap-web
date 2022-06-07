<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Deprecated Tags Mapping
    |--------------------------------------------------------------------------
    |
    | This option controls how deprecated tags should be handled in case they're
    | still posted to the app. The keys being the old tags & the values being
    | the mappings to the existing tags. Quantities are handled elsewhere.
    |
    */

    'deprecated_tags_mapping' => [
        'alcohol' => [
            'paperCardAlcoholPackaging' => ['alcohol' => 'packaging', 'material' => 'paper'],
            'plasticAlcoholPackaging' => ['alcohol' => 'packaging', 'material' => 'plastic'],
            'alcohol_plastic_cups' => ['alcohol' => 'cup', 'material' => 'plastic'],
        ],
        'coastal' => [
            'degraded_plasticbottle' => ['coastal' => 'degraded_bottle', 'material' => 'plastic'],
            'degraded_plasticbag' => ['coastal' => 'degraded_bag', 'material' => 'plastic'],
        ],
        'food' => [
            'paperFoodPackaging' => ['food' => 'packaging', 'material' => 'paper'],
            'plasticFoodPackaging' => ['food' => 'packaging', 'material' => 'plastic'],
            'plasticCutlery' => ['food' => 'cutlery', 'material' => 'plastic'],
            'glass_jar' => ['food' => 'jar', 'material' => 'glass'],
            'glass_jar_lid' => ['food' => 'jar_lid', 'material' => 'glass'],
            'aluminium_foil' => ['food' => 'foil', 'material' => 'aluminium'],
        ],
        'softdrinks' => [
            'plastic_cups' => ['softdrinks' => 'cup', 'material' => 'plastic'],
            'plastic_cup_tops' => ['softdrinks' => 'cup_top', 'material' => 'plastic'],
            'paper_cups' => ['softdrinks' => 'cup', 'material' => 'paper'],
        ],
        'smoking' => [
            'smoking_plastic' => ['smoking' => 'packaging', 'material' => 'plastic'],
        ]
    ],

];
