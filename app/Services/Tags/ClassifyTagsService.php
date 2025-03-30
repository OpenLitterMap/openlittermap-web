<?php

namespace App\Services\Tags;

use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CustomTagNew;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\Materials;

class ClassifyTagsService
{
    protected array $categories = [];
    protected array $objects = [];
    protected array $materials = [];
    protected array $brands = [];
    protected array $customTags = [];

    public function __construct()
    {
        $this->preloadCaches();
    }

    protected function preloadCaches(): void
    {
        $this->categories = Category::pluck('id', 'key')->all();
        $this->objects    = LitterObject::pluck('id', 'key')->all();
        $this->materials  = Materials::pluck('id', 'key')->all();
        $this->brands     = BrandList::pluck('id', 'key')->all();
        $this->customTags = CustomTagNew::pluck('id', 'key')->all();
    }

    protected function normalize(string $value): string
    {
        return strtolower(trim($value));
    }

    public function classify(string $rawTag): array
    {
        $key = $this->normalize($rawTag);

        if (isset($this->brands[$key])) {
            return ['type' => 'brand', 'id' => $this->brands[$key], 'key' => $key];
        }

        if (isset($this->objects[$key])) {
            return ['type' => 'object', 'id' => $this->objects[$key], 'key' => $key];
        }

        if (isset($this->materials[$key])) {
            return ['type' => 'material', 'id' => $this->materials[$key], 'key' => $key];
        }

        if (isset($this->categories[$key])) {
            return ['type' => 'category', 'id' => $this->categories[$key], 'key' => $key];
        }

        if (isset($this->customTags[$key])) {
            return ['type' => 'custom', 'id' => $this->customTags[$key], 'key' => $key];
        }

        return ['type' => 'undefined', 'key' => $key];
    }

    public function getCategory(string $key): Category
    {
        $key = $this->normalize($key);

        return Category::where(['key' => $key])->first();
    }
}
