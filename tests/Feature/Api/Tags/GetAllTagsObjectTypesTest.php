<?php

namespace Tests\Feature\Api\Tags;

use App\Enums\CategoryKey;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CategoryObject;
use App\Models\Litter\Tags\LitterObject;
use Database\Seeders\Tags\GenerateTagsSeeder;
use Tests\TestCase;

class GetAllTagsObjectTypesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GenerateTagsSeeder::class);
    }

    public function test_objects_include_types_from_tags_config(): void
    {
        $response = $this->getJson('/api/tags/all');

        $response->assertOk();

        $objects = collect($response->json('objects'));

        // "bottle" appears in alcohol (beer, wine, spirits, cider, unknown)
        // and softdrinks (water, soda, juice, energy, sports, tea, milk, smoothie, unknown)
        $bottle = $objects->firstWhere('key', 'bottle');
        $this->assertNotNull($bottle, 'bottle object should exist');
        $this->assertArrayHasKey('types', $bottle);

        $bottleTypes = $bottle['types'];
        $this->assertContains('beer', $bottleTypes);
        $this->assertContains('wine', $bottleTypes);
        $this->assertContains('water', $bottleTypes);
        $this->assertContains('soda', $bottleTypes);

        // No duplicates after merging across categories
        $this->assertCount(count(array_unique($bottleTypes)), $bottleTypes);
    }

    /**
     * The web picker builds its category chips from `objects[].categories`, not from
     * `category_objects`, so a tombstoned pairing has to be filtered out of that array too or
     * a pure category move leaves the old shelf selectable.
     */
    public function test_objects_categories_exclude_a_tombstoned_pairing(): void
    {
        $marine = Category::firstWhere('key', CategoryKey::Marine->value);
        $softdrinks = Category::firstWhere('key', CategoryKey::Softdrinks->value);
        $bottle = LitterObject::firstWhere('key', 'bottle');
        $source = CategoryObject::where('category_id', $marine->id)->where('litter_object_id', $bottle->id)->firstOrFail();
        $target = CategoryObject::where('category_id', $softdrinks->id)->where('litter_object_id', $bottle->id)->firstOrFail();
        $source->update(['merged_into_clo_id' => $target->id]);

        $object = collect($this->getJson('/api/tags/all')->assertOk()->json('objects'))->firstWhere('key', 'bottle');

        $this->assertNotContains($marine->id, array_column($object['categories'], 'id'), 'retired pairing must leave the picker');
        $this->assertContains($softdrinks->id, array_column($object['categories'], 'id'));
    }

    public function test_an_object_whose_only_pairing_is_tombstoned_leaves_the_picker(): void
    {
        $other = Category::firstWhere('key', CategoryKey::Other->value);
        $lonely = LitterObject::create(['key' => 'lonely_object']);
        $target = CategoryObject::where('category_id', $other->id)->firstOrFail();
        CategoryObject::create(['category_id' => $other->id, 'litter_object_id' => $lonely->id, 'merged_into_clo_id' => $target->id]);

        $keys = array_column($this->getJson('/api/tags/all')->assertOk()->json('objects'), 'key');

        $this->assertNotContains('lonely_object', $keys);
    }

    public function test_objects_without_types_return_empty_array(): void
    {
        $response = $this->getJson('/api/tags/all');

        $response->assertOk();

        $objects = collect($response->json('objects'));

        // "butts" has no types in TagsConfig
        $butts = $objects->firstWhere('key', 'butts');
        $this->assertNotNull($butts, 'butts object should exist');
        $this->assertArrayHasKey('types', $butts);
        $this->assertSame([], $butts['types']);
    }

    public function test_all_objects_have_types_key(): void
    {
        $response = $this->getJson('/api/tags/all');

        $response->assertOk();

        $objects = $response->json('objects');
        $this->assertGreaterThan(0, count($objects));

        foreach ($objects as $object) {
            $this->assertArrayHasKey('types', $object, "Object '{$object['key']}' missing types key");
            $this->assertIsArray($object['types']);
        }
    }

    public function test_existing_response_keys_unchanged(): void
    {
        $response = $this->getJson('/api/tags/all');

        $response->assertOk();
        $response->assertJsonStructure([
            'categories',
            'objects',
            'materials',
            'brands',
            'types',
            'category_objects',
            'category_object_types',
        ]);
    }
}
