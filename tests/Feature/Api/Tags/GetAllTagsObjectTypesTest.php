<?php

namespace Tests\Feature\Api\Tags;

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
