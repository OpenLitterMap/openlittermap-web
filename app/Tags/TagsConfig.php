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
                    'types' => ['beer', 'wine', 'spirits', 'cider', 'premixed', 'unknown'],
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
                    'types' => ['beer', 'cider', 'spirits', 'premixed', 'unknown'],
                ],
                'cup' => [
                    'materials' => ['plastic', 'paper', 'foam'],
                ],
                'glass' => [
                    'materials' => ['glass'],
                    'types' => ['beer', 'wine', 'spirits', 'cider', 'unknown'],
                ],
                'label' => [
                    'materials' => ['paper', 'plastic'],
                ],
                'packaging' => [
                    'materials' => ['cardboard', 'paper', 'plastic', 'foil'],
                ],
                'pull_ring' => [
                    'materials' => ['aluminium'],
                ],
                'six_pack_rings' => [
                    'materials' => ['plastic', 'cardboard'],
                ],
                'wine_cork' => [
                    'materials' => ['cork', 'plastic'],
                ],
                'other' => [],
            ],

            CategoryKey::Art->value => [
                'graffiti' => [],
                'mural' => [],
                'other' => [],
            ],

            CategoryKey::Civic->value => [
                'bags_litter' => [],
                'blocked_drain' => [],
                'fallen_tree' => [],
                'loose_cables' => [],
                'overflowing_bin' => [],
                'other' => [],
                'pothole' => [],
                'walkability' => [],
            ],

            CategoryKey::Coffee->value => [
                'cup' => [
                    'materials' => ['paper', 'plastic', 'foam', 'ceramic', 'metal', 'bioplastic'],
                ],
                'cup_carrier' => [
                    'materials' => ['cardboard'],
                ],
                'lid' => [
                    'materials' => ['plastic', 'paper', 'bioplastic'],
                ],
                'stirrer' => [
                    'materials' => ['wood', 'plastic', 'metal', 'bamboo'],
                ],
                'straw' => [
                    'materials' => ['plastic', 'paper'],
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
                    'types' => ['small', 'medium', 'large'],
                ],
                'other' => [],
            ],

            CategoryKey::Electronics->value => [
                'battery' => [
                    'materials' => ['metal', 'plastic'],
                    'types' => ['alkaline', 'lithium', 'rechargeable'],
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
                'light_bulb' => [
                    'materials' => ['glass', 'plastic', 'metal'],
                ],
                'phone' => [
                    'materials' => ['metal', 'plastic', 'glass'],
                ],
                'printer_cartridge' => [
                    'materials' => ['plastic'],
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
                'chopsticks' => [
                    'materials' => ['wood', 'bamboo', 'plastic'],
                ],
                'container' => [
                    'materials' => ['plastic'],
                ],
                'crisp_packet' => [
                    'materials' => ['foil', 'plastic'],
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
                'sachet' => [
                    'materials' => ['plastic', 'foil', 'paper'],
                ],
                'skewer' => [
                    'materials' => ['wood', 'bamboo', 'metal'],
                ],
                'straw' => [
                    'materials' => ['plastic', 'paper'],
                ],
                'takeaway_container' => [
                    'materials' => ['plastic', 'foam', 'aluminium', 'cardboard'],
                ],
                'tinfoil' => [
                    'materials' => ['aluminium'],
                ],
                'tray' => [
                    'materials' => ['plastic', 'foam', 'cardboard', 'aluminium'],
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
                'other' => [],
            ],

            CategoryKey::Marine->value => [
                'buoy' => [
                    'materials' => ['plastic', 'foam', 'metal'],
                ],
                'cotton_bud_stick' => [
                    'materials' => ['plastic'],
                ],
                'crate' => [
                    'materials' => ['plastic'],
                ],
                'fishing_hook' => [
                    'materials' => ['metal'],
                ],
                'fishing_line' => [
                    'materials' => ['nylon'],
                ],
                'fishing_lure' => [
                    'materials' => ['plastic', 'metal'],
                ],
                'fishing_net' => [
                    'materials' => ['nylon', 'plastic'],
                ],
                'macroplastics' => [
                    'materials' => ['plastic'],
                ],
                'microplastics' => [
                    'materials' => ['plastic'],
                ],
                'nurdles' => [
                    'materials' => ['plastic'],
                ],
                'polystyrene_fragment' => [
                    'materials' => ['polystyrene'],
                ],
                'rope' => [
                    'materials' => ['nylon', 'plastic', 'polyester'],
                    'types' => ['small', 'medium', 'large'],
                ],
                'shellfish_bag' => [
                    'materials' => ['plastic'],
                ],
                'shotgun_cartridge' => [
                    'materials' => ['metal', 'plastic'],
                ],
                'styrofoam' => [
                    'materials' => ['polystyrene'],
                ],
                'other' => [],
            ],

            CategoryKey::Medical->value => [
                'bandage' => [
                    'materials' => ['cotton', 'elastic'],
                ],
                'face_mask' => [
                    'materials' => ['cotton', 'polyester', 'paper'],
                ],
                'gloves' => [
                    'materials' => ['latex', 'rubber', 'plastic'],
                ],
                'inhaler' => [
                    'materials' => ['plastic', 'metal'],
                ],
                'medicine_bottle' => [
                    'materials' => ['plastic', 'glass'],
                ],
                'pill_pack' => [
                    'materials' => ['plastic', 'aluminium'],
                ],
                'plaster' => [
                    'materials' => ['plastic', 'fabric'],
                ],
                'sanitiser' => [
                    'materials' => ['plastic'],
                ],
                'syringe' => [
                    'materials' => ['plastic', 'metal'],
                ],
                'test_kit' => [
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
                'dogshit' => [],
                'dogshit_in_bag' => [
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
                'tissue' => [
                    'materials' => ['paper'],
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
                'ashtray' => [
                    'materials' => ['glass', 'ceramic', 'metal'],
                ],
                'box' => [
                    'types' => ['cigarette', 'match', 'unknown'],
                    'materials' => ['cardboard', 'foil'],
                ],
                'butts' => [
                    'materials' => ['plastic', 'paper'],
                ],
                'lighters' => [
                    'materials' => ['plastic', 'metal'],
                ],
                'packaging' => [
                    'materials' => ['plastic', 'foil'],
                ],
                'papers' => [
                    'materials' => ['paper'],
                ],
                'pouch' => [
                    'types' => ['tobacco'],
                    'materials' => ['plastic', 'foil'],
                ],
                'rolling_filter' => [
                    'materials' => ['paper', 'plastic'],
                ],
                'vape' => [
                    'types' => ['disposable', 'pen', 'device', 'pod', 'cartridge', 'mouthpiece', 'e_liquid_bottle', 'unknown'],
                    'materials' => ['plastic', 'metal', 'glass'],
                ],
                'other' => [],
            ],

            CategoryKey::Softdrinks->value => [
                'bottle' => [
                    'materials' => ['plastic', 'glass'],
                    'types' => ['water', 'soda', 'juice', 'energy', 'sports', 'tea', 'milk', 'smoothie', 'unknown'],
                ],
                'bottle_cap' => [
                    'materials' => ['plastic', 'metal'],
                ],
                'broken_glass' => [
                    'materials' => ['glass'],
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
                    'types' => ['tea', 'soda', 'smoothie', 'unknown'],
                ],
                'cup_carrier' => [
                    'materials' => ['cardboard', 'plastic'],
                ],
                'juice_pouch' => [
                    'materials' => ['plastic', 'foil'],
                ],
                'label' => [
                    'materials' => ['paper', 'plastic'],
                ],
                'lid' => [
                    'materials' => ['plastic', 'paper', 'bioplastic'],
                ],
                'packaging' => [
                    'materials' => ['cardboard', 'plastic', 'foil'],
                ],
                'pull_ring' => [
                    'materials' => ['aluminium'],
                ],
                'straw' => [
                    'materials' => ['plastic', 'paper', 'metal', 'bamboo'],
                ],
                'straw_wrapper' => [
                    'materials' => ['paper', 'plastic'],
                ],
                'other' => [],
            ],

            CategoryKey::Vehicles->value => [
                'battery' => [
                    'materials' => ['metal', 'plastic'],
                ],
                'bicycle' => [],
                'bumper' => [
                    'materials' => ['plastic', 'metal'],
                ],
                'car_part' => [
                    'materials' => ['metal', 'plastic', 'rubber', 'glass'],
                ],
                'hubcap' => [
                    'materials' => ['plastic'],
                ],
                'license_plate' => [
                    'materials' => ['metal', 'plastic'],
                ],
                'light' => [
                    'materials' => ['glass', 'plastic'],
                ],
                'mirror' => [
                    'materials' => ['glass', 'plastic'],
                ],
                'trim' => [
                    'materials' => ['plastic'],
                ],
                'tyre' => [
                    'materials' => ['rubber'],
                ],
                'wheel' => [
                    'materials' => ['metal', 'plastic', 'rubber'],
                ],
                'other' => [],
            ],
        ];
    }

    /**
     * Extract all unique material keys from the config.
     */
    public static function allMaterialKeys(): array
    {
        return array_values(array_unique(
            self::extractConfigKeys('materials')
        ));
    }

    /**
     * Extract all unique type keys from the config.
     */
    public static function allTypeKeys(): array
    {
        return array_values(array_unique(
            self::extractConfigKeys('types')
        ));
    }

    /**
     * Build a map of object key → merged values for a given config key.
     *
     * If the same object appears in multiple categories, values are merged and deduplicated.
     */
    public static function buildObjectMap(string $configKey): array
    {
        return self::buildObjectMaps($configKey)[$configKey];
    }

    /**
     * Build maps for multiple config keys in a single pass over the config.
     *
     * @return array<string, array<string, string[]>> Keyed by config key, then object key.
     */
    public static function buildObjectMaps(string ...$configKeys): array
    {
        $maps = array_fill_keys($configKeys, []);

        foreach (self::get() as $objects) {
            foreach ($objects as $objectKey => $config) {
                foreach ($configKeys as $key) {
                    $values = $config[$key] ?? [];

                    if (empty($values)) {
                        $maps[$key][$objectKey] ??= [];
                        continue;
                    }

                    $maps[$key][$objectKey] = array_values(array_unique(
                        array_merge($maps[$key][$objectKey] ?? [], $values)
                    ));
                }
            }
        }

        return $maps;
    }

    /**
     * Extract flat list of values for a config key across all objects.
     */
    private static function extractConfigKeys(string $key): array
    {
        $values = [];

        foreach (self::get() as $objects) {
            foreach ($objects as $config) {
                if (!empty($config[$key])) {
                    $values = array_merge($values, $config[$key]);
                }
            }
        }

        return $values;
    }
}
