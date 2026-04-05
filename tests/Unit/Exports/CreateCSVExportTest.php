<?php

namespace Tests\Unit\Exports;

use App\Exports\CreateCSVExport;
use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CategoryObject;
use App\Models\Litter\Tags\CustomTagNew;
use App\Models\Litter\Tags\LitterObjectType;
use App\Models\Litter\Tags\Materials;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Litter\Tags\PhotoTagExtraTags;
use App\Models\Photo;
use Database\Seeders\Tags\GenerateTagsSeeder;
use Tests\TestCase;

class CreateCSVExportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GenerateTagsSeeder::class);
    }

    public function test_it_has_correct_headings_for_all_categories_and_tags()
    {
        $expected = ['id', 'verification', 'phone', 'date_taken', 'date_uploaded', 'lat', 'lon', 'picked up', 'address', 'total_tags'];

        $categories = Category::with(['litterObjects' => fn ($q) => $q->orderBy('litter_objects.id')])->orderBy('id')->get();
        foreach ($categories as $category) {
            $expected[] = strtoupper($category->key);
            foreach ($category->litterObjects as $object) {
                $expected[] = $object->key;
            }
        }

        // Materials columns
        $expected[] = 'MATERIALS';
        foreach (Materials::orderBy('id')->get() as $material) {
            $expected[] = $material->key;
        }

        // Types columns
        $expected[] = 'TYPES';
        foreach (LitterObjectType::orderBy('id')->get() as $type) {
            $expected[] = $type->key;
        }

        // Brands + custom tags
        $expected[] = 'brands';
        $expected = array_merge($expected, ['custom_tag_1', 'custom_tag_2', 'custom_tag_3']);

        $this->assertDatabaseCount('photos', 0);

        $export = new CreateCSVExport('null', 1, null, null);

        $this->assertEquals($expected, $export->headings());
        $this->assertDatabaseCount('photos', 0);
    }

    public function test_it_has_correct_mappings_for_all_categories_and_tags()
    {
        // Pick a category with at least 2 objects (unclassified only has 1)
        $category = Category::with(['litterObjects' => fn ($q) => $q->orderBy('litter_objects.id')])
            ->orderBy('id')
            ->get()
            ->first(fn ($c) => $c->litterObjects->count() >= 2);
        $objects = $category->litterObjects;
        $obj1 = $objects[0];
        $obj2 = $objects[1];

        $cloId1 = CategoryObject::where('category_id', $category->id)->where('litter_object_id', $obj1->id)->value('id');
        $cloId2 = CategoryObject::where('category_id', $category->id)->where('litter_object_id', $obj2->id)->value('id');

        // Get a material for testing (seeded by GenerateTagsSeeder)
        $material = Materials::orderBy('id')->first();

        // Create a brand (not seeded)
        $brand = BrandList::firstOrCreate(['key' => 'test_brand_export']);

        // Nested summary format: { catId: { objId: { quantity, materials: {id: qty}, brands: {id: qty} } } }
        $photo = Photo::factory()->create([
            'verified' => 2,
            'model' => 'Redmi Note 8 pro',
            'datetime' => now()->toDateTimeString(),
            'lat' => 42.0,
            'lon' => 42.0,
            'remaining' => true,
            'address_array' => ['road' => '12345 Street', 'country' => 'Ireland'],
            'total_tags' => 15,
            'summary' => [
                'tags' => [
                    (string) $category->id => [
                        (string) $obj1->id => [
                            'quantity' => 5,
                            'materials' => [(string) $material->id => 5],
                            'brands' => [(string) $brand->id => 2],
                            'custom_tags' => (object) [],
                        ],
                        (string) $obj2->id => [
                            'quantity' => 10,
                            'materials' => (object) [],
                            'brands' => (object) [],
                            'custom_tags' => (object) [],
                        ],
                    ],
                ],
                'totals' => ['litter' => 15, 'materials' => 5, 'brands' => 2, 'custom_tags' => 0],
                'keys' => [
                    'brands' => [(string) $brand->id => 'test_brand_export'],
                ],
            ],
        ]);

        // Create custom tags via v5 photo_tags
        $customTag1 = CustomTagNew::firstOrCreate(['key' => 'my_custom_1']);
        $customTag2 = CustomTagNew::firstOrCreate(['key' => 'my_custom_2']);
        $customTag3 = CustomTagNew::firstOrCreate(['key' => 'my_custom_3']);
        $unclassifiedCloId = $this->getUnclassifiedOtherCloId();
        $pt1 = PhotoTag::create(['photo_id' => $photo->id, 'category_litter_object_id' => $unclassifiedCloId, 'quantity' => 1]);
        PhotoTagExtraTags::create(['photo_tag_id' => $pt1->id, 'tag_type' => 'custom_tag', 'tag_type_id' => $customTag1->id, 'quantity' => 1]);
        $pt2 = PhotoTag::create(['photo_id' => $photo->id, 'category_litter_object_id' => $unclassifiedCloId, 'quantity' => 1]);
        PhotoTagExtraTags::create(['photo_tag_id' => $pt2->id, 'tag_type' => 'custom_tag', 'tag_type_id' => $customTag2->id, 'quantity' => 1]);
        $pt3 = PhotoTag::create(['photo_id' => $photo->id, 'category_litter_object_id' => $unclassifiedCloId, 'quantity' => 1]);
        PhotoTagExtraTags::create(['photo_tag_id' => $pt3->id, 'tag_type' => 'custom_tag', 'tag_type_id' => $customTag3->id, 'quantity' => 1]);

        $this->assertDatabaseCount('photos', 1);

        $export = new CreateCSVExport('null', 1, null, null);

        // Build expected row
        $expected = [
            $photo->id,
            $photo->verified,
            'Redmi Note 8 pro',
            $photo->datetime,
            $photo->created_at,
            42.0,
            42.0,
            'No', // remaining=true means not picked up
            $photo->display_name,
            15, // total_objects from summary
        ];

        // Category/object columns — only the two tagged objects have values
        $allCategories = Category::with(['litterObjects' => fn ($q) => $q->orderBy('litter_objects.id')])->orderBy('id')->get();
        foreach ($allCategories as $cat) {
            $expected[] = null; // category separator
            foreach ($cat->litterObjects as $obj) {
                if ($cat->id === $category->id && $obj->id === $obj1->id) {
                    $expected[] = 5;
                } elseif ($cat->id === $category->id && $obj->id === $obj2->id) {
                    $expected[] = 10;
                } else {
                    $expected[] = null;
                }
            }
        }

        // Materials columns — material has qty 5 (from the nested materials object)
        $expected[] = null; // MATERIALS separator
        foreach (Materials::orderBy('id')->get() as $mat) {
            $expected[] = $mat->id === $material->id ? 5 : null;
        }

        // Types columns — no photo_tags with litter_object_type_id in this test
        $expected[] = null; // TYPES separator
        foreach (LitterObjectType::orderBy('id')->get() as $type) {
            $expected[] = null;
        }

        // Brands column
        $expected[] = 'test_brand_export:2';

        $expected = array_merge($expected, ['my_custom_1', 'my_custom_2', 'my_custom_3']);

        $this->assertEquals($expected, $export->map($photo->fresh()));
        $this->assertDatabaseCount('photos', 1);
    }

    public function test_it_maps_to_null_values_for_all_missing_categories()
    {
        $photo = Photo::factory()->create([
            'verified' => 2,
            'model' => 'Redmi Note 8 pro',
            'datetime' => now()->toDateTimeString(),
            'lat' => 42.0,
            'lon' => 42.0,
            'remaining' => true,
            'address_array' => ['road' => '12345 Street', 'country' => 'Ireland'],
            'summary' => [
                'tags' => (object) [],
                'totals' => ['litter' => 0, 'materials' => 0, 'brands' => 0, 'custom_tags' => 0],
            ],
        ]);

        $expected = [
            $photo->id,
            $photo->verified,
            'Redmi Note 8 pro',
            $photo->datetime,
            $photo->created_at,
            42.0,
            42.0,
            'No',
            $photo->display_name,
            0, // total_objects from summary
        ];

        $allCategories = Category::with(['litterObjects' => fn ($q) => $q->orderBy('litter_objects.id')])->orderBy('id')->get();
        foreach ($allCategories as $cat) {
            $expected[] = null; // category separator
            foreach ($cat->litterObjects as $obj) {
                $expected[] = null;
            }
        }

        // Materials — all null
        $expected[] = null;
        foreach (Materials::orderBy('id')->get() as $mat) {
            $expected[] = null;
        }

        // Types — all null
        $expected[] = null;
        foreach (LitterObjectType::orderBy('id')->get() as $type) {
            $expected[] = null;
        }

        // Brands — null
        $expected[] = null;

        $expected = array_merge($expected, [null, null, null]);

        $export = new CreateCSVExport('null', 1, null, null);

        $this->assertEquals($expected, $export->map($photo->fresh()));
    }

    public function test_materials_are_aggregated_across_multiple_tags()
    {
        $category = Category::with(['litterObjects' => fn ($q) => $q->orderBy('litter_objects.id')])
            ->orderBy('id')
            ->get()
            ->first(fn ($c) => $c->litterObjects->count() >= 2);
        $objects = $category->litterObjects;
        $obj1 = $objects[0];
        $obj2 = $objects[1];

        $material = Materials::orderBy('id')->first();

        // Nested summary: both objects have the same material with different quantities
        $photo = Photo::factory()->create([
            'verified' => 2,
            'datetime' => now()->toDateTimeString(),
            'lat' => 42.0,
            'lon' => 42.0,
            'address_array' => ['country' => 'Ireland'],
            'summary' => [
                'tags' => [
                    (string) $category->id => [
                        (string) $obj1->id => [
                            'quantity' => 3,
                            'materials' => [(string) $material->id => 3],
                            'brands' => (object) [],
                            'custom_tags' => (object) [],
                        ],
                        (string) $obj2->id => [
                            'quantity' => 7,
                            'materials' => [(string) $material->id => 7],
                            'brands' => (object) [],
                            'custom_tags' => (object) [],
                        ],
                    ],
                ],
                'totals' => ['litter' => 10, 'materials' => 10, 'brands' => 0, 'custom_tags' => 0],
            ],
        ]);

        $export = new CreateCSVExport('null', 1, null, null);
        $mapped = $export->map($photo->fresh());
        $headings = $export->headings();

        // Find the material column — search after the MATERIALS separator
        $materialsHeaderIndex = array_search('MATERIALS', $headings);
        $materialIndex = null;
        for ($i = $materialsHeaderIndex + 1; $i < count($headings); $i++) {
            if ($headings[$i] === $material->key) {
                $materialIndex = $i;
                break;
            }
        }

        $this->assertNotNull($materialIndex, "Material column '{$material->key}' not found in headings");
        // Material should be 3 + 7 = 10 (sum of both tag quantities)
        $this->assertEquals(10, $mapped[$materialIndex]);
    }

    public function test_brands_formatted_as_delimited_string()
    {
        $category = Category::with(['litterObjects' => fn ($q) => $q->orderBy('litter_objects.id')])
            ->orderBy('id')
            ->get()
            ->first(fn ($c) => $c->litterObjects->count() >= 1);
        $obj = $category->litterObjects->first();

        $brand1 = BrandList::firstOrCreate(['key' => 'test_brand_1']);
        $brand2 = BrandList::firstOrCreate(['key' => 'test_brand_2']);

        // Nested summary with brands as {id: qty} objects
        $photo = Photo::factory()->create([
            'verified' => 2,
            'datetime' => now()->toDateTimeString(),
            'lat' => 42.0,
            'lon' => 42.0,
            'address_array' => ['country' => 'Ireland'],
            'summary' => [
                'tags' => [
                    (string) $category->id => [
                        (string) $obj->id => [
                            'quantity' => 5,
                            'materials' => (object) [],
                            'brands' => [(string) $brand1->id => 1, (string) $brand2->id => 3],
                            'custom_tags' => (object) [],
                        ],
                    ],
                ],
                'totals' => ['litter' => 5, 'materials' => 0, 'brands' => 4, 'custom_tags' => 0],
                'keys' => [
                    'brands' => [(string) $brand1->id => 'test_brand_1', (string) $brand2->id => 'test_brand_2'],
                ],
            ],
        ]);

        $export = new CreateCSVExport('null', 1, null, null);
        $mapped = $export->map($photo->fresh());
        $headings = $export->headings();

        $brandsIndex = array_search('brands', $headings);
        $brandsValue = $mapped[$brandsIndex];

        $this->assertNotNull($brandsValue);
        $this->assertStringContainsString('test_brand_1:1', $brandsValue);
        $this->assertStringContainsString('test_brand_2:3', $brandsValue);
        $this->assertStringContainsString(';', $brandsValue);
    }

    public function test_types_are_mapped_from_photo_tags()
    {
        $category = Category::with(['litterObjects' => fn ($q) => $q->orderBy('litter_objects.id')])
            ->orderBy('id')
            ->get()
            ->first(fn ($c) => $c->litterObjects->count() >= 1);
        $obj = $category->litterObjects->first();
        $cloId = CategoryObject::where('category_id', $category->id)->where('litter_object_id', $obj->id)->value('id');

        $type = LitterObjectType::orderBy('id')->first();

        // Summary doesn't contain type_id — types come from photo_tags DB rows
        $photo = Photo::factory()->create([
            'verified' => 2,
            'datetime' => now()->toDateTimeString(),
            'lat' => 42.0,
            'lon' => 42.0,
            'address_array' => ['country' => 'Ireland'],
            'summary' => [
                'tags' => [
                    (string) $category->id => [
                        (string) $obj->id => [
                            'quantity' => 8,
                            'materials' => (object) [],
                            'brands' => (object) [],
                            'custom_tags' => (object) [],
                        ],
                    ],
                ],
                'totals' => ['litter' => 8, 'materials' => 0, 'brands' => 0, 'custom_tags' => 0],
            ],
        ]);

        // Create a photo_tag row with litter_object_type_id — this is the DB source for types
        PhotoTag::create([
            'photo_id' => $photo->id,
            'category_id' => $category->id,
            'litter_object_id' => $obj->id,
            'category_litter_object_id' => $cloId,
            'litter_object_type_id' => $type->id,
            'quantity' => 8,
        ]);

        $export = new CreateCSVExport('null', 1, null, null);
        $mapped = $export->map($photo->fresh());
        $headings = $export->headings();

        // Find the type column — search after TYPES separator
        $typesHeaderIndex = array_search('TYPES', $headings);
        $typeIndex = null;
        for ($i = $typesHeaderIndex + 1; $i < count($headings); $i++) {
            if ($headings[$i] === $type->key) {
                $typeIndex = $i;
                break;
            }
        }

        $this->assertNotNull($typeIndex, "Type column '{$type->key}' not found in headings");
        $this->assertEquals(8, $mapped[$typeIndex]);

        // Other types should be null
        $otherType = LitterObjectType::where('id', '!=', $type->id)->orderBy('id')->first();
        if ($otherType) {
            $otherIndex = null;
            for ($i = $typesHeaderIndex + 1; $i < count($headings); $i++) {
                if ($headings[$i] === $otherType->key) {
                    $otherIndex = $i;
                    break;
                }
            }
            $this->assertNull($mapped[$otherIndex]);
        }
    }
}
