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
        ]
    ],

];
