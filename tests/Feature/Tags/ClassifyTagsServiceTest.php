<?php

namespace Tests\Feature\Tags;

use App\Services\Tags\ClassifyTagsService;
use Database\Seeders\Tags\GenerateTagsSeeder;
use Tests\TestCase;

class ClassifyTagsServiceTest extends TestCase
{
    private ClassifyTagsService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GenerateTagsSeeder::class);
        $this->service = app(ClassifyTagsService::class);
    }

    /** @test */
    public function test_get_category_resolves_standard_keys(): void
    {
        $category = $this->service->getCategory('smoking');
        $this->assertNotNull($category);
        $this->assertEquals('smoking', $category->key);
    }

    /** @test */
    public function test_get_category_resolves_coastal_to_marine(): void
    {
        $category = $this->service->getCategory('coastal');
        $this->assertNotNull($category);
        $this->assertEquals('marine', $category->key);
    }

    /** @test */
    public function test_get_category_resolves_trashdog_to_pets(): void
    {
        $category = $this->service->getCategory('trashdog');
        $this->assertNotNull($category);
        $this->assertEquals('pets', $category->key);
    }

    /** @test */
    public function test_get_category_resolves_dogshit_to_pets(): void
    {
        $category = $this->service->getCategory('dogshit');
        $this->assertNotNull($category);
        $this->assertEquals('pets', $category->key);
    }

    /** @test */
    public function test_get_category_resolves_automobile_to_vehicles(): void
    {
        $category = $this->service->getCategory('automobile');
        $this->assertNotNull($category);
        $this->assertEquals('vehicles', $category->key);
    }

    /** @test */
    public function test_get_category_resolves_pathway_to_unclassified(): void
    {
        $category = $this->service->getCategory('pathway');
        $this->assertNotNull($category);
        $this->assertEquals('unclassified', $category->key);
    }

    /** @test */
    public function test_get_category_resolves_drugs_to_unclassified(): void
    {
        $category = $this->service->getCategory('drugs');
        $this->assertNotNull($category);
        $this->assertEquals('unclassified', $category->key);
    }

    /** @test */
    public function test_get_category_resolves_political_to_unclassified(): void
    {
        $category = $this->service->getCategory('political');
        $this->assertNotNull($category);
        $this->assertEquals('unclassified', $category->key);
    }

    /** @test */
    public function test_get_category_resolves_stationery_to_unclassified(): void
    {
        $category = $this->service->getCategory('stationery');
        $this->assertNotNull($category);
        $this->assertEquals('unclassified', $category->key);
    }

    /** @test */
    public function test_get_category_returns_null_for_nonexistent_key(): void
    {
        $category = $this->service->getCategory('nonexistent_category_xyz');
        $this->assertNull($category);
    }

    /** @test */
    public function test_get_category_normalizes_key_casing(): void
    {
        $category = $this->service->getCategory('Smoking');
        $this->assertNotNull($category);
        $this->assertEquals('smoking', $category->key);
    }

    /** @test */
    public function test_all_category_aliases_resolve_to_existing_categories(): void
    {
        $aliases = [
            'coastal'    => 'marine',
            'trashdog'   => 'pets',
            'dogshit'    => 'pets',
            'automobile' => 'vehicles',
            'pathway'    => 'unclassified',
            'drugs'      => 'unclassified',
            'political'  => 'unclassified',
            'stationery' => 'unclassified',
        ];

        foreach ($aliases as $deprecated => $expected) {
            $category = $this->service->getCategory($deprecated);
            $this->assertNotNull($category, "Alias '{$deprecated}' should resolve to '{$expected}' but returned null");
            $this->assertEquals($expected, $category->key, "Alias '{$deprecated}' should resolve to '{$expected}' but got '{$category->key}'");
        }
    }
}
