<?php

namespace Tests\Feature\Tags;

use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CategoryObject;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\LitterObjectType;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Photo;
use App\Models\Users\User;
use Database\Seeders\Tags\GenerateTagsSeeder;
use Database\Seeders\Tags\SeedLitterObjectTypesSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TaggingArchitecturePhase1Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            GenerateTagsSeeder::class,
            SeedLitterObjectTypesSeeder::class,
        ]);
    }

    // ─── Seeding verification ───

    public function test_17_litter_object_types_are_seeded(): void
    {
        $this->assertDatabaseCount('litter_object_types', 17);
    }

    public function test_all_expected_type_keys_exist(): void
    {
        $expectedKeys = [
            'beer', 'wine', 'spirits', 'cider', 'water', 'soda', 'juice',
            'energy', 'sports', 'coffee', 'tea', 'milk', 'smoothie',
            'iced_tea', 'sparkling_water', 'plant_milk', 'unknown',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertDatabaseHas('litter_object_types', ['key' => $key]);
        }
    }

    public function test_unclassified_category_is_seeded(): void
    {
        $this->assertDatabaseHas('categories', ['key' => 'unclassified']);
    }

    public function test_beverages_category_is_seeded(): void
    {
        $this->assertDatabaseHas('categories', ['key' => 'beverages']);
    }

    public function test_canonical_objects_are_seeded(): void
    {
        $canonicalObjects = ['broken_glass', 'carton', 'straw_wrapper', 'coffee_pod'];

        foreach ($canonicalObjects as $key) {
            $this->assertDatabaseHas('litter_objects', ['key' => $key]);
        }
    }

    public function test_9_typed_clos_exist(): void
    {
        $expectedCombos = [
            ['alcohol', 'bottle'],
            ['alcohol', 'can'],
            ['alcohol', 'pint_glass'],
            ['alcohol', 'wine_glass'],
            ['alcohol', 'shot_glass'],
            ['beverages', 'bottle'],
            ['beverages', 'can'],
            ['beverages', 'carton'],
            ['beverages', 'cup'],
        ];

        foreach ($expectedCombos as [$catKey, $objKey]) {
            $category = Category::where('key', $catKey)->first();
            $object = LitterObject::where('key', $objKey)->first();

            $this->assertNotNull($category, "Category '{$catKey}' should exist");
            $this->assertNotNull($object, "Object '{$objKey}' should exist");

            $this->assertDatabaseHas('category_litter_object', [
                'category_id' => $category->id,
                'litter_object_id' => $object->id,
            ]);
        }
    }

    public function test_every_typed_clo_includes_unknown(): void
    {
        $unknown = LitterObjectType::where('key', 'unknown')->first();
        $this->assertNotNull($unknown);

        $typedCloIds = DB::table('category_object_types')
            ->distinct()
            ->pluck('category_litter_object_id');

        foreach ($typedCloIds as $cloId) {
            $hasUnknown = DB::table('category_object_types')
                ->where('category_litter_object_id', $cloId)
                ->where('litter_object_type_id', $unknown->id)
                ->exists();

            $this->assertTrue($hasUnknown, "CLO id={$cloId} must include 'unknown' type");
        }
    }

    public function test_alcohol_bottle_has_correct_types(): void
    {
        $clo = $this->getClo('alcohol', 'bottle');
        $typeKeys = $clo->types->pluck('key')->sort()->values()->toArray();

        $this->assertEquals(['beer', 'cider', 'spirits', 'unknown', 'wine'], $typeKeys);
    }

    public function test_beverages_can_has_correct_types(): void
    {
        $clo = $this->getClo('beverages', 'can');
        $typeKeys = $clo->types->pluck('key')->sort()->values()->toArray();

        $this->assertEquals(['energy', 'iced_tea', 'juice', 'soda', 'sparkling_water', 'unknown'], $typeKeys);
    }

    // ─── Model relationship tests ───

    public function test_category_object_types_relationship(): void
    {
        $clo = $this->getClo('alcohol', 'bottle');

        $this->assertGreaterThan(0, $clo->types->count());
        $this->assertInstanceOf(LitterObjectType::class, $clo->types->first());
    }

    public function test_photo_tag_category_object_relationship(): void
    {
        $category = Category::where('key', 'alcohol')->first();
        $object = LitterObject::where('key', 'bottle')->first();
        $clo = CategoryObject::where('category_id', $category->id)
            ->where('litter_object_id', $object->id)
            ->first();

        $photo = Photo::factory()->create();
        $photoTag = PhotoTag::create([
            'photo_id' => $photo->id,
            'category_id' => $category->id,
            'litter_object_id' => $object->id,
            'category_litter_object_id' => $clo->id,
            'quantity' => 1,
        ]);

        $this->assertNotNull($photoTag->categoryObject);
        $this->assertEquals($clo->id, $photoTag->categoryObject->id);
    }

    public function test_photo_tag_type_relationship(): void
    {
        $type = LitterObjectType::where('key', 'beer')->first();
        $category = Category::where('key', 'alcohol')->first();
        $object = LitterObject::where('key', 'bottle')->first();
        $clo = CategoryObject::where('category_id', $category->id)
            ->where('litter_object_id', $object->id)
            ->first();

        $photo = Photo::factory()->create();
        $photoTag = PhotoTag::create([
            'photo_id' => $photo->id,
            'category_id' => $category->id,
            'litter_object_id' => $object->id,
            'category_litter_object_id' => $clo->id,
            'litter_object_type_id' => $type->id,
            'quantity' => 1,
        ]);

        $this->assertNotNull($photoTag->type);
        $this->assertEquals('beer', $photoTag->type->key);
    }

    public function test_photo_tag_type_is_nullable(): void
    {
        $category = Category::where('key', 'alcohol')->first();
        $object = LitterObject::where('key', 'bottle')->first();
        $clo = CategoryObject::where('category_id', $category->id)
            ->where('litter_object_id', $object->id)
            ->first();

        $photo = Photo::factory()->create();
        $photoTag = PhotoTag::create([
            'photo_id' => $photo->id,
            'category_id' => $category->id,
            'litter_object_id' => $object->id,
            'category_litter_object_id' => $clo->id,
            'litter_object_type_id' => null,
            'quantity' => 1,
        ]);

        $this->assertNull($photoTag->type);
    }

    public function test_photo_tag_clo_id_is_required(): void
    {
        $category = Category::where('key', 'alcohol')->first();
        $object = LitterObject::where('key', 'bottle')->first();

        $photo = Photo::factory()->create();

        $this->expectException(\Illuminate\Database\QueryException::class);

        PhotoTag::create([
            'photo_id' => $photo->id,
            'category_id' => $category->id,
            'litter_object_id' => $object->id,
            'category_litter_object_id' => null,
            'quantity' => 1,
        ]);
    }

    // ─── API tests ───

    public function test_get_all_tags_returns_types(): void
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

    public function test_get_all_tags_excludes_unclassified(): void
    {
        $response = $this->getJson('/api/tags/all');

        $response->assertOk();

        $categoryKeys = collect($response->json('categories'))->pluck('key')->toArray();
        $this->assertNotContains('unclassified', $categoryKeys);
    }

    public function test_get_all_tags_types_have_correct_structure(): void
    {
        $response = $this->getJson('/api/tags/all');

        $response->assertOk();

        $types = $response->json('types');
        $this->assertGreaterThanOrEqual(17, count($types));

        $firstType = $types[0];
        $this->assertArrayHasKey('id', $firstType);
        $this->assertArrayHasKey('key', $firstType);
        $this->assertArrayHasKey('name', $firstType);
    }

    public function test_get_all_tags_category_objects_have_correct_structure(): void
    {
        $response = $this->getJson('/api/tags/all');

        $categoryObjects = $response->json('category_objects');
        $this->assertGreaterThan(0, count($categoryObjects));

        $first = $categoryObjects[0];
        $this->assertArrayHasKey('id', $first);
        $this->assertArrayHasKey('category_id', $first);
        $this->assertArrayHasKey('litter_object_id', $first);
    }

    public function test_get_all_tags_category_object_types_have_correct_structure(): void
    {
        $response = $this->getJson('/api/tags/all');

        $categoryObjectTypes = $response->json('category_object_types');
        $this->assertGreaterThan(0, count($categoryObjectTypes));

        $first = $categoryObjectTypes[0];
        $this->assertArrayHasKey('category_litter_object_id', $first);
        $this->assertArrayHasKey('litter_object_type_id', $first);
    }

    // ─── Seeder idempotency ───

    public function test_seeder_is_idempotent(): void
    {
        $typeCountBefore = LitterObjectType::count();
        $cotCountBefore = DB::table('category_object_types')->count();

        // Run seeder again
        $this->seed(SeedLitterObjectTypesSeeder::class);

        $this->assertEquals($typeCountBefore, LitterObjectType::count());
        $this->assertEquals($cotCountBefore, DB::table('category_object_types')->count());
    }

    // ─── Helpers ───

    protected function getClo(string $categoryKey, string $objectKey): CategoryObject
    {
        $category = Category::where('key', $categoryKey)->first();
        $object = LitterObject::where('key', $objectKey)->first();

        return CategoryObject::where('category_id', $category->id)
            ->where('litter_object_id', $object->id)
            ->first();
    }
}
