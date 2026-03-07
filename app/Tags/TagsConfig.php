<?php

namespace App\Tags;

use App\Enums\CategoryKey;

class TagsConfig
{
    /**
     * Get the complete tags configuration.
     *
     * Canonical objects per category. All keys are snake_case.
     * Categories are ordered alphabetically.
     * Types represent "what was in the container" (beer, water, soda, etc.)
     */
    public static function get(): array
    {
        return [
            CategoryKey::Alcohol->value => [
                'bottle' => [
                    'types' => ['beer', 'wine', 'spirits', 'cider'],
                    'materials' => ['glass', 'plastic'],
                ],
                'broken_glass' => [
                    'materials' => ['glass'],
                ],
                'bottle_cap' => [
                    'materials' => ['metal', 'plastic', 'cork'],
                ],
                'can' => [
                    'materials' => ['aluminium'],
                    'types' => ['beer', 'cider', 'spirits'],
                ],
                'cup' => [
                    'materials' => ['plastic'],
                ],
                'glass' => [
                    'materials' => ['glass'],
                    'types' => ['beer', 'wine', 'spirits', 'cider'],
                ],
                'packaging' => [
                    'materials' => ['cardboard', 'paper', 'plastic'],
                ],
                'pull_ring' => [
                    'materials' => ['aluminium'],
                ],
                'six_pack_rings' => [
                    'materials' => ['plastic'],
                ],
                'other' => [],
            ],

            CategoryKey::Art->value => [
                'graffiti' => [],
                'mural' => [],
                'other' => [],
            ],

            CategoryKey::Coffee->value => [
                'cup' => [
                    'materials' => ['paper', 'plastic', 'foam', 'ceramic', 'metal'],
                ],
                'lid' => [
                    'materials' => ['plastic', 'paper', 'bioplastic'],
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
                'sleeve' => [
                    'materials' => ['cardboard', 'silicone'],
                ],
                'other' => [],
            ],

            CategoryKey::Dumping->value => [
                'dumping' => [
                    'sizes' => ['small', 'medium', 'large'],
                ],
                'other' => [],
            ],

            CategoryKey::Electronics->value => [
                'battery' => [
                    'materials' => ['metal', 'plastic'],
                    'types' => ['alkaline', 'lithium'],
                ],
                'cable' => [
                    'materials' => ['plastic', 'copper'],
                ],
                'charger' => [
                    'materials' => ['plastic', 'metal'],
                ],
                'headphones' => [
                    'materials' => ['plastic', 'metal'],
                ],
                'phone' => [
                    'materials' => ['metal', 'plastic', 'glass'],
                ],
                'other' => [],
            ],

            CategoryKey::Food->value => [
                'bag' => [
                    'materials' => ['plastic', 'paper', 'cloth', 'bioplastic'],
                ],
                'box' => [
                    'materials' => ['cardboard', 'plastic', 'wood', 'metal'],
                ],
                'can' => [
                    'materials' => ['aluminium', 'steel'],
                ],
                'crisp_packet' => [
                    'materials' => ['foil'],
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
                    'materials' => ['paper', 'cloth'],
                ],
                'tinfoil' => [
                    'materials' => ['aluminium'],
                ],
                'wrapper' => [
                    'materials' => ['plastic', 'paper', 'foil', 'bioplastic'],
                ],
                'other' => [],
            ],

            CategoryKey::Industrial->value => [
                'oil_container' => [
                    'materials' => ['plastic', 'metal'],
                ],
                'oil_drum' => [
                    'materials' => ['metal', 'plastic'],
                ],
                'chemical_container' => [
                    'materials' => ['plastic', 'metal', 'glass'],
                ],
                'construction' => [
                    'materials' => ['clay', 'concrete', 'plastic', 'metal', 'fiberglass', 'foam', 'asphalt', 'ceramic', 'stone'],
                ],
                'bricks' => [
                    'materials' => ['clay', 'concrete', 'stone'],
                ],
                'tape' => [
                    'materials' => ['plastic'],
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
                'dumping_small' => [],
                'dumping_medium' => [],
                'dumping_large' => [],
                'other' => [],
            ],

            CategoryKey::Marine->value => [
                'fishing_net' => [
                    'materials' => ['nylon', 'plastic'],
                ],
                'rope' => [
                    'materials' => ['nylon', 'plastic'],
                ],
                'buoy' => [
                    'materials' => ['plastic'],
                ],
                'crate' => [
                    'materials' => ['plastic'],
                ],
                'microplastics' => [
                    'materials' => ['plastic'],
                ],
                'macroplastics' => [
                    'materials' => ['plastic'],
                ],
                'styrofoam' => [
                    'materials' => ['polystyrene'],
                ],
                'shotgun_cartridge' => [
                    'materials' => ['metal', 'plastic'],
                ],
                'other' => [],
            ],

            CategoryKey::Medical->value => [
                'syringe' => [
                    'materials' => ['plastic', 'metal'],
                ],
                'pill_pack' => [
                    'materials' => ['plastic', 'aluminium'],
                ],
                'medicine_bottle' => [
                    'materials' => ['plastic', 'glass'],
                ],
                'bandage' => [
                    'materials' => ['cotton', 'elastic'],
                ],
                'plaster' => [
                    'materials' => ['plastic'],
                ],
                'gloves' => [
                    'materials' => ['latex', 'rubber', 'plastic'],
                ],
                'face_mask' => [
                    'materials' => ['cotton', 'polyester', 'paper'],
                ],
                'sanitiser' => [
                    'materials' => ['plastic'],
                ],
                'other' => [],
            ],

            CategoryKey::Other->value => [
                'clothing' => [],
                'bags_litter' => [],
                'overflowing_bin' => [],
                'plastic' => [],
                'traffic_cone' => [],
                'metal' => [],
                'plastic_bag' => [],
                'paper' => [
                    'materials' => ['paper'],
                ],
                'poster' => [],
                'cable_tie' => [],
                'balloon' => [
                    'materials' => ['plastic', 'latex'],
                ],
                'furniture' => [],
                'mattress' => [],
                'appliance' => [],
                'paint_can' => [
                    'materials' => ['metal', 'plastic'],
                ],
                'umbrella' => [
                    'materials' => ['plastic', 'metal', 'cloth'],
                ],
                'other' => [],
            ],

            CategoryKey::Pets->value => [
                'dog_waste' => [],
                'dog_waste_in_bag' => [
                    'materials' => ['plastic'],
                ],
                'other' => [],
            ],

            CategoryKey::Sanitary->value => [
                'wipes' => [
                    'materials' => ['polyester', 'plastic'],
                ],
                'nappies' => [
                    'materials' => ['plastic', 'cloth'],
                ],
                'ear_swabs' => [
                    'materials' => ['plastic', 'cotton'],
                ],
                'toothbrush' => [
                    'materials' => ['plastic', 'nylon', 'bamboo', 'wood'],
                ],
                'toothpaste_tube' => [
                    'materials' => ['plastic', 'aluminium'],
                ],
                'dental_floss' => [
                    'materials' => ['nylon', 'plastic'],
                ],
                'deodorant_can' => [
                    'materials' => ['aluminium'],
                ],
                'sanitary_pad' => [
                    'materials' => ['cotton', 'plastic'],
                ],
                'tampon' => [
                    'materials' => ['plastic'],
                ],
                'menstrual_cup' => [
                    'materials' => ['plastic'],
                ],
                'condom' => [
                    'materials' => ['latex'],
                ],
                'condom_wrapper' => [
                    'materials' => ['plastic', 'foil'],
                ],
                'other' => [],
            ],

            CategoryKey::Smoking->value => [
                'butts' => [
                    'materials' => ['plastic', 'paper'],
                ],
                'lighters' => [
                    'materials' => ['plastic', 'metal'],
                ],
                'box' => [
                    'types' => ['cigarette', 'match', 'unknown'],
                    'materials' => ['cardboard', 'foil'],
                ],
                'pouch' => [
                    'types' => ['tobacco'],
                    'materials' => ['plastic'],
                ],
                'papers' => [
                    'materials' => ['paper'],
                ],
                'packaging' => [
                    'materials' => ['plastic', 'foil'],
                ],
                'vape' => [
                    'types' => ['pen', 'cartridge'],
                    'materials' => ['plastic', 'metal', 'glass'],
                ],
                'ashtray' => [
                    'materials' => ['glass', 'ceramic', 'metal'],
                ],
                'other' => [],
            ],

            CategoryKey::Softdrinks->value => [
                'bottle' => [
                    'materials' => ['plastic', 'glass'],
                    'types' => ['water', 'soda', 'juice', 'energy', 'sports', 'tea', 'milk', 'smoothie', 'unknown'],
                ],
                'can' => [
                    'materials' => ['aluminium'],
                    'types' => ['soda', 'energy', 'juice', 'iced_tea', 'sparkling_water', 'unknown'],
                ],
                'carton' => [
                    'materials' => ['cardboard', 'foil', 'plastic'],
                    'types' => ['juice', 'milk', 'iced_tea', 'plant_milk', 'unknown'],
                ],
                'cup' => [
                    'materials' => ['paper', 'plastic', 'foam', 'ceramic', 'metal'],
                    'types' => ['coffee', 'tea', 'soda', 'smoothie', 'unknown'],
                ],
                'lid' => [
                    'materials' => ['plastic', 'paper', 'bioplastic'],
                ],
                'straw' => [
                    'materials' => ['plastic', 'paper', 'metal', 'bamboo'],
                ],
                'straw_wrapper' => [
                    'materials' => ['paper', 'plastic'],
                ],
                'juice_pouch' => [
                    'materials' => ['plastic', 'foil'],
                ],
                'coffee_pod' => [
                    'materials' => ['plastic', 'aluminium'],
                ],
                'label' => [
                    'materials' => ['paper', 'plastic'],
                ],
                'broken_glass' => [
                    'materials' => ['glass'],
                ],
                'packaging' => [
                    'materials' => ['cardboard', 'plastic', 'foil'],
                ],
                'other' => [],
            ],

            CategoryKey::Unclassified->value => [
                'other' => [],
                'bags_litter' => [],
            ],

            CategoryKey::Vehicles->value => [
                'car_part' => [
                    'materials' => ['metal', 'plastic', 'rubber', 'glass'],
                ],
                'battery' => [
                    'materials' => ['metal', 'plastic'],
                ],
                'bumper' => [
                    'materials' => ['plastic', 'metal'],
                ],
                'tyre' => [
                    'materials' => ['rubber'],
                ],
                'wheel' => [
                    'materials' => ['metal'],
                ],
                'light' => [
                    'materials' => ['glass', 'plastic'],
                ],
                'mirror' => [
                    'materials' => ['glass', 'plastic'],
                ],
                'license_plate' => [
                    'materials' => ['metal', 'plastic'],
                ],
                'other' => [],
            ],
        ];
    }
}
