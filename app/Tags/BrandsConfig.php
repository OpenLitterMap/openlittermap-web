<?php

namespace App\Tags;

/**
 * Definitive Brand-Object Configuration for OpenLitterMap v5
 */
class BrandsConfig
{
    public const BRAND_OBJECTS = [
        // A
        'aadrink' => [
            'softdrinks' => ['sports_bottle', 'label'],
        ],

        'adidas' => [
            'other' => ['clothing'],
        ],

        'aldi' => [
            'food' => ['packaging'],
            'other' => ['plasticBags'],
        ],

        'amazon' => [
            'other' => ['packaging', 'plastic'],
        ],

        'amstel' => [
            'alcohol' => ['beer_bottle', 'beer_can', 'bottletops'],
        ],

        'apple' => [
            'other' => ['packaging'],
        ],

        'applegreen' => [
            'food' => ['packaging', 'wrapper'],
            'coffee' => ['cup', 'lid'],
        ],

        'asahi' => [
            'alcohol' => ['beer_bottle', 'beer_can'],
        ],

        'avoca' => [
            'food' => ['packaging', 'wrapper'],
            'coffee' => ['cup', 'lid'],
        ],

        // B
        'bacardi' => [
            'alcohol' => ['spirits_bottle'],
        ],

        'ballygowan' => [
            'softdrinks' => ['water_bottle', 'label'],
        ],

        'bewleys' => [
            'coffee' => ['cup', 'lid', 'sleeve'],
            'food' => ['wrapper', 'packaging'],
        ],

        'budweiser' => [
            'alcohol' => ['beer_bottle', 'beer_can', 'bottletops', 'packaging', 'six_pack_rings'],
        ],

        'bullit' => [
            'softdrinks' => ['energy_can'],
        ],

        'bulmers' => [
            'alcohol' => ['beer_bottle', 'beer_can', 'bottletops'],
        ],

        'burgerking' => [
            'food' => ['wrapper', 'packaging', 'napkins', 'plate'],
            'coffee' => ['cup', 'lid'],
            'softdrinks' => ['cup', 'lid', 'straws'],
        ],

        // C
        'cadburys' => [
            'food' => ['wrapper'],
        ],

        'cafe_nero' => [
            'coffee' => ['cup', 'lid', 'sleeve', 'stirrer'],
        ],

        'calanda' => [
            'alcohol' => ['beer_bottle', 'beer_can'],
        ],

        'camel' => [
            'smoking' => ['cigarette_box', 'butts', 'packaging'],
        ],

        'caprisun' => [
            'softdrinks' => ['juice_carton', 'straws'],
        ],

        'carlsberg' => [
            'alcohol' => ['beer_bottle', 'beer_can', 'bottletops', 'packaging', 'six_pack_rings'],
        ],

        'centra' => [
            'food' => ['packaging', 'wrapper'],
            'coffee' => ['cup'],
            'other' => ['plasticBags'],
        ],

        'circlek' => [
            'food' => ['packaging'],
            'coffee' => ['cup'],
            'other' => ['plasticBags'],
        ],

        'coke' => [
            'softdrinks' => ['soda_can', 'fizzy_bottle', 'water_bottle', 'cup', 'lid', 'label', 'straws', 'pullRing'],
        ],

        'colgate' => [
            'sanitary' => ['toothbrush', 'other'],
        ],

        'corona' => [
            'alcohol' => ['beer_bottle', 'bottletops', 'packaging'],
        ],

        'costa' => [
            'coffee' => ['cup', 'lid', 'sleeve', 'stirrer'],
            'food' => ['wrapper', 'packaging', 'napkins'],
        ],

        // D
        'doritos' => [
            'food' => ['crisp_large', 'packet'],
        ],

        'drpepper' => [
            'softdrinks' => ['soda_can', 'fizzy_bottle', 'cup', 'lid'],
        ],

        'dunnes' => [
            'food' => ['packaging'],
            'other' => ['plasticBags'],
        ],

        'duracell' => [
            'other' => ['batteries'],
        ],

        'durex' => [
            'sanitary' => ['condom'],
        ],

        // E
        'esquires' => [
            'coffee' => ['cup', 'lid', 'sleeve'],
        ],

        'evian' => [
            'softdrinks' => ['water_bottle', 'label'],
        ],

        // F
        'fanta' => [
            'softdrinks' => ['soda_can', 'fizzy_bottle', 'cup', 'lid'],
        ],

        'fernandes' => [
            'softdrinks' => ['soda_can', 'fizzy_bottle'],
        ],

        'fosters' => [
            'alcohol' => ['beer_can', 'packaging'],
        ],

        'frank_and_honest' => [
            'coffee' => ['cup', 'lid', 'sleeve'],
        ],

        'fritolay' => [
            'food' => ['crisp_small', 'crisp_large', 'packet'],
        ],

        // G
        'gatorade' => [
            'softdrinks' => ['sports_bottle', 'label'],
        ],

        'gillette' => [
            'sanitary' => ['razor'],
        ],

        'goldenpower' => [
            'softdrinks' => ['energy_can'],
        ],

        'guinness' => [
            'alcohol' => ['beer_can', 'beer_bottle', 'bottletops'],
        ],

        // H
        'haribo' => [
            'food' => ['wrapper', 'packet'],
        ],

        'heineken' => [
            'alcohol' => ['beer_bottle', 'beer_can', 'bottletops', 'packaging', 'six_pack_rings'],
        ],

        'hertog_jan' => [
            'alcohol' => ['beer_bottle', 'beer_can'],
        ],

        // I
        'insomnia' => [
            'coffee' => ['cup', 'lid', 'sleeve'],
            'food' => ['wrapper', 'packaging'],
        ],

        // K
        'kellogs' => [
            'food' => ['packaging'],
        ],

        'kfc' => [
            'food' => ['packaging', 'napkins', 'plate', 'wrapper'],
            'softdrinks' => ['cup', 'lid', 'straws'],
        ],

        // L
        'lavish' => [
            'softdrinks' => ['sports_bottle'],
        ],

        'lego' => [
            'other' => ['plastic'],
        ],

        'lidl' => [
            'food' => ['packaging'],
            'other' => ['plasticBags'],
        ],

        'lindenvillage' => [
            'food' => ['packaging'],
        ],

        'lipton' => [
            'softdrinks' => ['fizzy_bottle', 'label'],
        ],

        'loreal' => [
            'sanitary' => ['bottle', 'other'],
        ],

        'lucozade' => [
            'softdrinks' => ['sports_bottle', 'energy_can', 'fizzy_bottle', 'label'],
        ],

        // M
        'marlboro' => [
            'smoking' => ['cigarette_box', 'butts', 'packaging'],
        ],

        'mars' => [
            'food' => ['wrapper'],
        ],

        'mcdonalds' => [
            'food' => ['wrapper', 'packaging', 'napkins', 'plate', 'cutlery'],
            'coffee' => ['cup', 'lid'],
            'softdrinks' => ['cup', 'lid', 'straws'],
        ],

        'modelo' => [
            'alcohol' => ['beer_bottle', 'beer_can'],
        ],

        'molson_coors' => [
            'alcohol' => ['beer_bottle', 'beer_can'],
        ],

        'monster' => [
            'softdrinks' => ['energy_can', 'label'],
        ],

        // N
        'nero' => [
            'coffee' => ['cup', 'lid', 'sleeve', 'stirrer'],
            'food' => ['wrapper', 'packaging'],
        ],

        'nescafe' => [
            'coffee' => ['cup', 'lid', 'packaging'],
        ],

        'nestle' => [
            'food' => ['wrapper', 'packaging'],
            'coffee' => ['cup', 'lid'],
        ],

        'nike' => [
            'other' => ['clothing'],
        ],

        // O
        'obriens' => [
            'food' => ['wrapper', 'packaging'],
            'coffee' => ['cup', 'lid'],
        ],

        'ok_' => [  // OK convenience store brand
            'food' => ['packaging'],
            'other' => ['plasticBags'],
        ],

        // P
        'pepsi' => [
            'softdrinks' => ['soda_can', 'fizzy_bottle', 'cup', 'lid', 'label', 'straws', 'pullRing'],
            // NOT in food - wrappers belong to food brands
        ],

        'powerade' => [
            'softdrinks' => ['sports_bottle', 'label'],
        ],

        // R
        'redbull' => [
            'softdrinks' => ['energy_can', 'label'],
        ],

        'ribena' => [
            'softdrinks' => ['juice_bottle', 'juice_carton', 'label'],
        ],

        // S
        'sainsburys' => [
            'food' => ['packaging'],
            'other' => ['plasticBags'],
        ],

        'samsung' => [
            'other' => ['packaging'],
        ],

        'schutters' => [
            'alcohol' => ['beer_bottle'],
        ],

        'seven_eleven' => [
            'food' => ['packaging'],
            'coffee' => ['cup'],
            'softdrinks' => ['cup'],
        ],

        'slammers' => [
            'softdrinks' => ['energy_can'],
        ],

        'spa' => [
            'softdrinks' => ['water_bottle'],
        ],

        'spar' => [
            'food' => ['packaging', 'wrapper'],
            'other' => ['plasticBags'],
        ],

        'sprite' => [
            'softdrinks' => ['soda_can', 'fizzy_bottle', 'cup', 'lid'],
        ],

        'starbucks' => [
            'coffee' => ['cup', 'lid', 'sleeve', 'stirrer'],
            'food' => ['wrapper', 'packaging', 'napkins'],
        ],

        'stella' => [
            'alcohol' => ['beer_bottle', 'beer_can', 'bottletops', 'packaging'],
        ],

        'subway' => [
            'food' => ['wrapper', 'packaging', 'napkins'],
            'softdrinks' => ['cup', 'lid', 'straws'],
        ],

        'supermacs' => [
            'food' => ['wrapper', 'packaging', 'napkins', 'plate'],
            'coffee' => ['cup', 'lid'],
            'softdrinks' => ['cup', 'lid', 'straws'],
        ],

        'supervalu' => [
            'food' => ['packaging'],
            'other' => ['plasticBags'],
        ],

        // T
        'tayto' => [
            'food' => ['crisp_small', 'packet'],
        ],

        'tesco' => [
            'food' => ['packaging', 'wrapper'],
            'other' => ['plasticBags'],
        ],

        'thins' => [
            'food' => ['crisp_small', 'packet'],
        ],

        'tim_hortons' => [
            'coffee' => ['cup', 'lid', 'sleeve'],
            'food' => ['wrapper', 'packaging', 'napkins'],
        ],

        // V
        'volvic' => [
            'softdrinks' => ['water_bottle', 'label'],
        ],

        // W
        'waitrose' => [
            'food' => ['packaging'],
            'other' => ['plasticBags'],
        ],

        'walkers' => [
            'food' => ['crisp_small', 'crisp_large', 'packet'],
        ],

        'wendys' => [
            'food' => ['wrapper', 'packaging', 'napkins'],
            'softdrinks' => ['cup', 'lid', 'straws'],
        ],

        'wilde_and_greene' => [
            'food' => ['packaging', 'wrapper'],
            'coffee' => ['cup'],
        ],

        'winston' => [
            'smoking' => ['cigarette_box', 'butts', 'packaging'],
        ],

        'woolworths' => [
            'food' => ['packaging'],
            'other' => ['plasticBags'],
        ],

        'wrigleys' => [
            'food' => ['wrapper'],
        ],
    ];

    /**
     * Get allowed objects for a brand in a category
     */
    public static function getAllowedObjects(string $brandKey, string $categoryKey): array
    {
        return self::BRAND_OBJECTS[$brandKey][$categoryKey] ?? [];
    }

    /**
     * Check if a brand can attach to a specific object
     */
    public static function canBrandAttachToObject(
        string $brandKey,
        string $categoryKey,
        string $objectKey
    ): bool {
        $allowedObjects = self::getAllowedObjects($brandKey, $categoryKey);
        return in_array($objectKey, $allowedObjects);
    }

    /**
     * Get all categories a brand can appear in
     */
    public static function getBrandCategories(string $brandKey): array
    {
        return array_keys(self::BRAND_OBJECTS[$brandKey] ?? []);
    }

    /**
     * Get primary category for a brand (first in list)
     */
    public static function getPrimaryCategory(string $brandKey): ?string
    {
        $categories = self::getBrandCategories($brandKey);
        return $categories[0] ?? null;
    }

    /**
     * Check if a brand is configured
     */
    public static function brandExists(string $brandKey): bool
    {
        return isset(self::BRAND_OBJECTS[$brandKey]);
    }

    /**
     * Get all configured brands
     */
    public static function getAllBrands(): array
    {
        return array_keys(self::BRAND_OBJECTS);
    }

    /**
     * Get unconfigured brands from a list
     */
    public static function getUnconfiguredBrands(array $brandKeys): array
    {
        return array_diff($brandKeys, self::getAllBrands());
    }

    /**
     * Build pivot relationships from this config
     */
    public static function buildPivots(): array
    {
        $pivots = [];

        foreach (self::BRAND_OBJECTS as $brandKey => $categories) {
            foreach ($categories as $categoryKey => $objects) {
                foreach ($objects as $objectKey) {
                    $pivots[] = [
                        'brand' => $brandKey,
                        'category' => $categoryKey,
                        'object' => $objectKey,
                    ];
                }
            }
        }

        return $pivots;
    }
}
