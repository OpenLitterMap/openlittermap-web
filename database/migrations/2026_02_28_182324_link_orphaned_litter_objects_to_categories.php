<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Link 17 orphaned litter_objects to their correct categories
 * in category_litter_object. These objects exist but had no
 * category association, making them invisible in the tag browser.
 */
return new class extends Migration
{
    /**
     * Object key => [category keys] mapping.
     * Some objects belong in multiple categories.
     */
    private array $mappings = [
        // Alcohol
        'wine_bottle'    => ['alcohol'],
        'beer_bottle'    => ['alcohol'],
        'beer_can'       => ['alcohol'],
        'spirits_bottle' => ['alcohol'],
        'bottletops'     => ['alcohol', 'soft_drinks'],

        // Food
        'crisp_small'    => ['food'],
        'crisp_large'    => ['food'],
        'glass_jar'      => ['food'],

        // Smoking
        'rollingPapers'  => ['smoking'],
        'tobaccopouch'   => ['smoking'],
        'filters'        => ['smoking'],
        'vapeOil'        => ['smoking'],
        'vapePen'        => ['smoking'],

        // Industrial
        'chemical'       => ['industrial'],
        'oil'            => ['industrial'],

        // Unclassified
        'brokenglass'    => ['unclassified'],
        'plastic'        => ['unclassified'],
    ];

    public function up(): void
    {
        $now = now();

        // Resolve category keys to IDs
        $categories = DB::table('categories')
            ->pluck('id', 'key')
            ->all();

        // Resolve object keys to IDs
        $objectKeys = array_keys($this->mappings);
        $objects = DB::table('litter_objects')
            ->whereIn('key', $objectKeys)
            ->pluck('id', 'key')
            ->all();

        $rows = [];
        foreach ($this->mappings as $objectKey => $categoryKeys) {
            $objectId = $objects[$objectKey] ?? null;
            if (!$objectId) {
                continue;
            }

            foreach ($categoryKeys as $catKey) {
                $categoryId = $categories[$catKey] ?? null;
                if (!$categoryId) {
                    continue;
                }

                $rows[] = [
                    'category_id'      => $categoryId,
                    'litter_object_id' => $objectId,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ];
            }
        }

        // Insert with ignore to skip any that already exist
        foreach ($rows as $row) {
            DB::table('category_litter_object')->insertOrIgnore($row);
        }
    }

    public function down(): void
    {
        $categories = DB::table('categories')
            ->pluck('id', 'key')
            ->all();

        $objectKeys = array_keys($this->mappings);
        $objects = DB::table('litter_objects')
            ->whereIn('key', $objectKeys)
            ->pluck('id', 'key')
            ->all();

        foreach ($this->mappings as $objectKey => $categoryKeys) {
            $objectId = $objects[$objectKey] ?? null;
            if (!$objectId) {
                continue;
            }

            foreach ($categoryKeys as $catKey) {
                $categoryId = $categories[$catKey] ?? null;
                if (!$categoryId) {
                    continue;
                }

                DB::table('category_litter_object')
                    ->where('category_id', $categoryId)
                    ->where('litter_object_id', $objectId)
                    ->delete();
            }
        }
    }
};
