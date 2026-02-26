<?php

namespace App\Tags;

class TagsConfig
{
    /**
     * Get the complete tags configuration
     *
     * @return array
     */
    public static function get(): array
    {
        return [
            'alcohol' => [
                'beer_bottle' => [
                    'materials' => ['glass'],
                ],
                'cider_bottle' => [
                    'materials' => ['glass', 'plastic'],
                ],
                'spirits_bottle' => [
                    'materials' => ['glass'],
                ],
                'wine_bottle' => [
                    'materials' => ['glass'],
                ],
                'beer_can' => [
                    'materials' => ['aluminium'],
                ],
                'spirits_can' => [
                    'materials' => ['aluminium'],
                ],
                'cider_can' => [
                    'materials' => ['aluminium'],
                ],
                'wine_glass' => [
                    'materials' => ['glass'],
                ],
                'pint_glass' => [
                    'materials' => ['glass'],
                ],
                'shot_glass' => [
                    'materials' => ['glass'],
                ],
                'bottleTop' => [
                    'materials' => ['metal', 'plastic', 'cork'],
                ],
                'brokenGlass' => [
                    'materials' => ['glass'],
                ],
                'cup' => [
                    'materials' => ['plastic'],
                ],
                'packaging' => [
                    'materials' => ['cardboard', 'paper', 'plastic'],
                ],
                'pull_ring' => [
                    'materials' => ['aluminium'],
                ],
                'straw' => [
                    'materials' => ['plastic', 'paper', 'metal'],
                ],
                'sixPackRings' => [
                    'materials' => ['plastic'],
                ],
                'other' => [],
            ],

            'automobile' => [
                'car_part' => [
                    'materials' => ['metal', 'plastic', 'rubber', 'glass'],
                ],
                'battery' => [
                    'materials' => ['metal', 'plastic'],
                ],
                'alloy' => [
                    'materials' => ['metal'],
                ],
                'bumper' => [
                    'materials' => ['plastic', 'metal'],
                ],
                'exhaust' => [
                    'materials' => ['metal'],
                ],
                'engine' => [
                    'materials' => ['metal'],
                ],
                'mirror' => [
                    'materials' => ['glass', 'plastic'],
                ],
                'light' => [
                    'materials' => ['glass', 'plastic'],
                ],
                'license_plate' => [
                    'materials' => ['metal', 'plastic'],
                ],
                'oil_can' => [
                    'materials' => ['metal', 'plastic'],
                ],
                'tyre' => [
                    'materials' => ['rubber'],
                ],
                'wheel' => [
                    'materials' => ['metal'],
                ],
                'other' => [],
            ],

            'coastal' => [
                'bag' => [
                    'materials' => ['plastic'],
                    'states' => ['degraded'],
                ],
                'bottle' => [
                    'materials' => ['plastic'],
                    'states' => ['degraded'],
                ],
                'buoys' => [
                    'materials' => ['plastic'],
                ],
                'plastics' => [
                    'materials' => ['plastic'],
                    'sizes' => ['micro', 'macro'],
                    'states' => ['degraded']
                ],
                'rope' => [
                    'materials' => ['rope', 'plastic'],
                    'sizes' => ['small', 'medium', 'large'],
                ],
                'fishing_nets' => [
                    'materials' => ['rope', 'plastic'],
                ],
                // These are already in softdrinks
                'straws' => [
                    'materials' => ['plastic'],
                    'states' => ['degraded'],
                ],
                // already in smoking
                'lighters' => [
                    'materials' => ['plastic'],
                    'states' => ['degraded'],
                ],
                // already in other
                'balloons' => [
                    'materials' => ['plastic', 'latex'],
                ],
                // toy
                'lego' => [
                    'materials' => ['plastic'],
                ],
                // other
                'shotgun_cartridges' => [
                    'materials' => ['metal', 'plastic'],
                ],
                // industrial?
                'styrofoam' => [
                    'materials' => ['styrofoam'],
                    'sizes' => ['small', 'medium', 'large'],
                ],
                'other' => [],
            ],

            'coffee' => [
                'cup' => [
                    'materials' => ['paper', 'plastic', 'foam', 'ceramic', 'metal'],
                ],
                'lid' => [
                    'materials' => ['plastic', 'paper', 'bioplastic', 'plantFiber'],
                ],
                'stirrer' => [
                    'materials' => ['wood', 'plastic', 'metal', 'bamboo'],
                ],
                'packaging' => [
                    'materials' => ['plastic', 'foil', 'paper'],
                ],
                'pod' => [
                    'materials' => ['plastic', 'aluminium'],
                ],
                'sleeves' => [
                    'materials' => ['cardboard', 'silicone'],
                ],
                'other' => [],
            ],

            'electronics' => [
                'battery' => [
                    'materials' => ['metal'],
                ],
                'cable' => [
                    'materials' => ['plastic', 'copper'],
                ],
                'mobilePhone' => [
                    'materials' => ['metal', 'plastic', 'glass'],
                ],
                'laptop' => [
                    'materials' => ['metal', 'plastic', 'glass'],
                ],
                'tablet' => [
                    'materials' => ['metal', 'plastic', 'glass'],
                ],
                'charger' => [
                    'materials' => ['plastic', 'metal'],
                ],
                'headphones' => [
                    'materials' => ['plastic', 'metal'],
                ],
                'other' => [],
            ],

            'dumping' => [
                'dumping' => [
                    'sizes' => ['small', 'medium', 'large'],
                ],
            ],

            'food' => [
                'bag' => [
                    'materials' => ['plastic', 'paper', 'cloth', 'bioplastic'],
                ],
                'box' => [
                    'materials' => ['cardboard', 'plastic', 'wood', 'metal'],
                ],
                'can' => [
                    'materials' => ['aluminium', 'steel'],
                ],
                'crisps' => [
                    'materials' => ['foil'],
                    'sizes' => ['small', 'medium', 'large'],
                ],
                'cutlery' => [
                    'materials' => ['plastic', 'wood', 'bioplastic', 'bamboo', 'metal'],
                ],
                'gum' => [
                    'materials' => ['rubber'],
                ],
                'jar' => [
                    'materials' => ['glass', 'plastic', 'metal'],
                ],
                'lid' => [
                    'materials' => ['ceramic', 'metal', 'plastic', 'glass'],
                ],
                'packet' => [
                    'materials' => ['plastic', 'foil', 'paper'],
                ],
                'packaging' => [
                    'materials' => ['plastic', 'paper', 'foam', 'cardboard', 'bioplastic'],
                ],
                'plate' => [
                    'materials' => ['plastic', 'paper', 'foam', 'ceramic', 'metal', 'glass', 'bioplastic'],
                ],
                'pizza_box' => [
                    'materials' => ['cardboard'],
                ],
                'napkins' => [
                    'materials' => ['paper', 'cloth', 'biodegradable'],
                ],
                'tinfoil' => [
                    'materials' => ['aluminium'],
                ],
                'wrapper' => [
                    'materials' => ['plastic', 'paper', 'foil', 'bioplastic'],
                ],
                'other' => [],
            ],

            'industrial' => [
                'oil' => [
                    'materials' => ['oil'],
                ],
                'oilDrum' => [
                    'materials' => ['metal', 'plastic'],
                ],
                'chemical' => [
                    'materials' => ['chemical'],
                ],
                'plastic' => [
                    'materials' => ['plastic'],
                ],
                'construction' => [
                    'materials' => ['clay', 'concrete', 'plastic', 'metal', 'fiberglass', 'foam', 'asphalt', 'ceramic', 'stone'],
                ],
                'bricks' => [
                    'materials' => ['clay', 'concrete', 'stone'],
                ],
                'tape' => [
                    'materials' => ['plastic', 'adhesive'],
                ],
                'pallet' => [
                    'materials' => ['wood', 'plastic'],
                ],
                'wire' => [
                    'materials' => ['copper', 'plastic', 'steel'],
                ],
                'pipe' => [
                    'materials' => ['metal', 'plastic', 'concrete'],
                ],
                'container' => [
                    'materials' => ['metal', 'plastic'],
                ],
                'other' => [],
            ],

            'other' => [
                'clothing' => [],
                'randomLitter' => [],
                'bagsLitter' => [],
                'overflowingBins' => [],
                'plastic' => [],
                'trafficCone' => [],
                'metal' => [],
                'plasticBags' => [],
                'paper' => [
                    'materials' => ['paper'],
                ],
                'posters' => [],
                'cableTie' => [],
                'washingUp' => [],
                'balloons' => [],
                'life_buoy' => [],
                'furniture' => [],
                'mattress' => [],
                'appliance' => [],
                'paintCan' => [],
                'other' => [],
                'graffiti' => [],
                'umbrella' => [
                    'materials' => ['plastic', 'metal', 'cloth'],
                ],
            ],

            'pets' => [
                'dogshit' => [],
                'dogshit_in_bag' => [
                    'materials' => ['plastic'],
                ],
            ],

            'sanitary' => [
                'gloves' => [
                    'materials' => ['latex', 'rubber', 'plastic'],
                ],
                'facemask' => [
                    'materials' => ['cotton', 'polyester', 'paper'],
                ],
                'condoms' => [
                    'materials' => ['latex'],
                ],
                'condom_wrapper' => [
                    'materials' => ['plastic', 'foil'],
                ],
                'nappies' => [
                    'materials' => ['plastic', 'cloth', 'biodegradable'],
                ],
                'sanitaryPad' => [
                    'materials' => ['cotton', 'plastic'],
                ],
                'tampon' => [
                    'materials' => ['plastic'],
                ],
                'deodorant_can' => [
                    'materials' => ['aluminium'],
                ],
                'menstrual' => [
                    'materials' => ['plastic'],
                ],
                'earSwabs' => [
                    'materials' => ['plastic', 'cotton'],
                ],
                'toothbrush' => [
                    'materials' => ['plastic', 'nylon', 'bamboo', 'wood'],
                ],
                'toothpasteTube' => [
                    'materials' => ['plastic', 'aluminium'],
                ],
                'toothpasteBox' => [
                    'materials' => ['cardboard'],
                ],
                'dentalFloss' => [
                    'materials' => ['nylon', 'plastic'],
                ],
                'mouthwashBottle' => [
                    'materials' => ['plastic', 'glass'],
                ],
                'wipes' => [
                    'materials' => ['fabric', 'plastic', 'biodegradable'],
                ],
                'sanitiser' => [
                    'materials' => ['plastic'],
                ],
                'syringe' => [
                    'materials' => ['plastic', 'metal'],
                ],
                'bandage' => [
                    'materials' => ['cotton', 'elastic'],
                ],
                'plaster' => [
                    'materials' => ['plastic', 'adhesive'],
                ],
                'medicineBottle' => [
                    'materials' => ['plastic', 'glass'],
                ],
                'pillPack' => [
                    'materials' => ['plastic', 'aluminium'],
                ],
                'other' => [],
            ],

            'smoking' => [
                'butts' => [
                    'materials' => ['plastic', 'paper'],
                ],
                'lighters' => [
                    'materials' => ['plastic', 'metal'],
                ],
                'cigarette_box' => [
                    'materials' => ['cardboard'],
                ],
                'match_box' => [
                    'materials' => ['cardboard'],
                ],
                'tobaccoPouch' => [
                    'materials' => ['plastic'],
                ],
                'rollingPapers' => [
                    'materials' => ['paper'],
                ],
                'packaging' => [
                    'materials' => ['cellophane', 'foil'],
                ],
                'filters' => [
                    'materials' => ['plastic', 'biodegradable'],
                ],
                'vapePen' => [
                    'materials' => ['plastic', 'metal'],
                ],
                'vapeOil' => [
                    'materials' => ['plastic', 'glass'],
                ],
                'pipe' => [
                    'materials' => ['glass', 'metal', 'ceramic'],
                ],
                'bong' => [
                    'materials' => ['glass', 'metal', 'ceramic'],
                ],
                'grinder' => [
                    'materials' => ['metal', 'plastic'],
                ],
                'ashtray' => [
                    'materials' => ['glass', 'ceramic', 'metal'],
                ],
                'other' => [],
            ],

            'softdrinks' => [
                'water_bottle' => [
                    'materials' => ['plastic', 'glass'],
                ],
                'fizzy_bottle' => [
                    'materials' => ['plastic', 'glass'],
                ],
                'juice_bottle' => [
                    'materials' => ['plastic', 'glass'],
                ],
                'energy_bottle' => [
                    'materials' => ['plastic', 'glass'],
                ],
                'sports_bottle' => [
                    'materials' => ['plastic', 'glass'],
                ],
                'iceTea_bottle' => [
                    'materials' => ['plastic', 'glass'],
                ],
                'milk_bottle' => [
                    'materials' => ['plastic', 'glass'],
                ],
                'smoothie_bottle' => [
                    'materials' => ['plastic', 'glass'],
                ],
                'soda_can' => [
                    'materials' => ['aluminium'],
                ],
                'energy_can' => [
                    'materials' => ['aluminium'],
                ],
                'juice_can' => [
                    'materials' => ['aluminium'],
                ],
                'icedTea_can' => [
                    'materials' => ['aluminium'],
                ],
                'sparklingWater_can' => [
                    'materials' => ['aluminium'],
                ],
                'juice_carton' => [
                    'materials' => ['cardboard', 'foil', 'plastic'],
                ],
                'milk_carton' => [
                    'materials' => ['cardboard', 'foil', 'plastic'],
                ],
                'icedTea_carton' => [
                    'materials' => ['cardboard', 'foil', 'plastic'],
                ],
                'plantMilk_carton' => [
                    'materials' => ['cardboard', 'foil', 'plastic'],
                ],
                'cup' => [
                    'materials' => ['plastic', 'paper', 'foam'],
                ],
                'drinkingGlass' => [
                    'materials' => ['glass'],
                ],
                'brokenGlass' => [
                    'materials' => ['glass'],
                ],
                'lid' => [
                    'materials' => ['plastic'],
                ],
                'label' => [
                    'materials' => ['paper', 'plastic'],
                ],
                'pullRing' => [
                    'materials' => ['aluminium'],
                ],
                'packaging' => [
                    'materials' => ['cardboard', 'plastic', 'foil'],
                ],
                'straw' => [
                    'materials' => ['plastic', 'paper', 'metal', 'bamboo'],
                ],
                'straw_packaging' => [
                    'materials' => ['paper', 'plastic'],
                ],
                'juice_pouch' => [
                    'materials' => ['plastic', 'foil'],
                ],
                'other' => [],
            ],

            'stationery' => [
                'book' => [
                    'materials' => ['paper'],
                ],
                'pen' => [
                    'materials' => ['plastic', 'metal'],
                ],
                'pencil' => [
                    'materials' => ['wood', 'graphite'],
                ],
                'magazine' => [
                    'materials' => ['paper', 'plastic'],
                ],
                'marker' => [
                    'materials' => ['plastic'],
                ],
                'notebook' => [
                    'materials' => ['paper'],
                ],
                'stapler' => [
                    'materials' => ['metal', 'plastic'],
                ],
                'paperClip' => [
                    'materials' => ['metal'],
                ],
                'rubberBand' => [
                    'materials' => ['rubber'],
                ],
                'other' => [],
            ],
        ];
    }
}
