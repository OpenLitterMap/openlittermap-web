<?php

namespace App\Traits;

use App\Models\Litter\Tags\Materials;
use App\Models\Litter\Tags\LitterState;
use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\CustomTagNew;

trait ManagesTaggables
{
    /**
     * Attach multiple tag types based on configuration
     *
     * @param array $configuration
     */
    public function attachTagsFromConfiguration(array $configuration): void
    {
        if (!empty($configuration['materials'])) {
            $this->attachMaterialsByKey($configuration['materials']);
        }

        if (!empty($configuration['states'])) {
            $this->attachStatesByKey($configuration['states']);
        }

        if (!empty($configuration['brands'])) {
            $this->attachBrandsByKey($configuration['brands']);
        }

        if (!empty($configuration['custom_tags'])) {
            $this->attachCustomTagsByKey($configuration['custom_tags']);
        }

        if (!empty($configuration['sizes'])) {
            $this->attachSizesByKey($configuration['sizes']);
        }
    }

    /**
     * Attach materials by their keys
     *
     * @param array $materialKeys
     */
    public function attachMaterialsByKey(array $materialKeys): void
    {
        $ids = [];
        foreach ($materialKeys as $key) {
            $material = Materials::firstOrCreate(['key' => $key]);
            $ids[] = $material->id;
        }

        if (!empty($ids) && method_exists($this, 'materials')) {
            $this->materials()->syncWithoutDetaching($ids);
        }
    }

    /**
     * Attach states by their keys
     *
     * @param array $stateKeys
     */
    public function attachStatesByKey(array $stateKeys): void
    {
        $ids = [];
        foreach ($stateKeys as $key) {
            $state = LitterState::firstOrCreate(['key' => $key]);
            $ids[] = $state->id;
        }

        // Note: You'll need to add a states() relationship to CategoryObject
        if (!empty($ids) && method_exists($this, 'states')) {
            $this->states()->syncWithoutDetaching($ids);
        }
    }

    /**
     * Attach brands by their keys
     *
     * @param array $brandKeys
     */
    public function attachBrandsByKey(array $brandKeys): void
    {
        $ids = [];
        foreach ($brandKeys as $key) {
            $brand = BrandList::firstOrCreate(['key' => $key]);
            $ids[] = $brand->id;
        }

        if (!empty($ids) && method_exists($this, 'brands')) {
            $this->brands()->syncWithoutDetaching($ids);
        }
    }

    /**
     * Attach custom tags by their keys
     *
     * @param array $customTagKeys
     */
    public function attachCustomTagsByKey(array $customTagKeys): void
    {
        $ids = [];
        foreach ($customTagKeys as $key) {
            $customTag = CustomTagNew::firstOrCreate(['key' => $key]);
            $ids[] = $customTag->id;
        }

        if (!empty($ids) && method_exists($this, 'customTags')) {
            $this->customTags()->syncWithoutDetaching($ids);
        }
    }

    /**
     * Attach sizes (treating them as special states)
     *
     * @param array $sizes
     */
    public function attachSizesByKey(array $sizes): void
    {
        $stateKeys = array_map(fn($size) => 'size_' . $size, $sizes);
        $this->attachStatesByKey($stateKeys);
    }
}
