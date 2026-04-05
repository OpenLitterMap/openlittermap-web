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
use App\Models\Users\User;
use Database\Seeders\Tags\GenerateTagsSeeder;
use Tests\TestCase;

class CreateCSVExportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GenerateTagsSeeder::class);
    }

    public function test_empty_export_has_only_fixed_columns()
    {
        $expected = ['id', 'verification', 'phone', 'date_taken', 'date_uploaded', 'lat', 'lon', 'picked up', 'address', 'total_tags'];

        $export = new CreateCSVExport('null', 1, null, null);

        $this->assertEquals($expected, $export->headings());
    }

    public function test_headings_include_only_columns_with_data()
    {
        $category = Category::with(['litterObjects' => fn ($q) => $q->orderBy('litter_objects.id')])
            ->orderBy('id')
            ->get()
            ->first(fn ($c) => $c->litterObjects->count() >= 2);
        $obj1 = $category->litterObjects[0];
        $obj2 = $category->litterObjects[1];

        $cloId1 = CategoryObject::where('category_id', $category->id)->where('litter_object_id', $obj1->id)->value('id');

        $material = Materials::orderBy('id')->first();
        $type = LitterObjectType::orderBy('id')->first();
        $brand = BrandList::firstOrCreate(['key' => 'test_brand']);
        $customTag = CustomTagNew::firstOrCreate(['key' => 'my_custom']);

        // Create a user photo with specific tags
        $user = User::factory()->create();
        $photo = Photo::factory()->create(['verified' => 2, 'user_id' => $user->id]);
        $pt = PhotoTag::create([
            'photo_id' => $photo->id,
            'category_id' => $category->id,
            'litter_object_id' => $obj1->id,
            'category_litter_object_id' => $cloId1,
            'litter_object_type_id' => $type->id,
            'quantity' => 3,
        ]);
        PhotoTagExtraTags::create(['photo_tag_id' => $pt->id, 'tag_type' => 'material', 'tag_type_id' => $material->id, 'quantity' => 1]);
        PhotoTagExtraTags::create(['photo_tag_id' => $pt->id, 'tag_type' => 'brand', 'tag_type_id' => $brand->id, 'quantity' => 1]);
        PhotoTagExtraTags::create(['photo_tag_id' => $pt->id, 'tag_type' => 'custom_tag', 'tag_type_id' => $customTag->id, 'quantity' => 1]);

        $export = new CreateCSVExport(null, null, null, $user->id);
        $headings = $export->headings();

        // Fixed columns always present
        $this->assertEquals('id', $headings[0]);
        $this->assertEquals('total_tags', $headings[9]);

        // Only the used category + its used object should appear (not all categories)
        $this->assertContains(strtoupper($category->key), $headings);
        $this->assertContains($obj1->key, $headings);
        // obj2 is in the same category but has no photo_tags — should be excluded
        $this->assertNotContains($obj2->key, $headings);

        // Only the used material, type, brand, custom_tag sections should appear
        $this->assertContains('MATERIALS', $headings);
        $this->assertContains($material->key, $headings);
        $this->assertContains('TYPES', $headings);
        $this->assertContains($type->key, $headings);
        $this->assertContains('brands', $headings);
        $this->assertContains('custom_tag_1', $headings);

        // Unused materials/types should NOT appear
        $otherMaterial = Materials::where('id', '!=', $material->id)->orderBy('id')->first();
        if ($otherMaterial) {
            $this->assertNotContains($otherMaterial->key, $headings);
        }
    }

    public function test_it_has_correct_mappings()
    {
        $category = Category::with(['litterObjects' => fn ($q) => $q->orderBy('litter_objects.id')])
            ->orderBy('id')
            ->get()
            ->first(fn ($c) => $c->litterObjects->count() >= 2);
        $obj1 = $category->litterObjects[0];
        $obj2 = $category->litterObjects[1];

        $cloId1 = CategoryObject::where('category_id', $category->id)->where('litter_object_id', $obj1->id)->value('id');
        $cloId2 = CategoryObject::where('category_id', $category->id)->where('litter_object_id', $obj2->id)->value('id');

        $material = Materials::orderBy('id')->first();
        $brand = BrandList::firstOrCreate(['key' => 'test_brand_export']);

        $user = User::factory()->create();
        $photo = Photo::factory()->create([
            'verified' => 2,
            'user_id' => $user->id,
            'model' => 'Redmi Note 8 pro',
            'datetime' => now()->toDateTimeString(),
            'lat' => 42.0,
            'lon' => 42.0,
            'remaining' => true,
            'address_array' => ['road' => '12345 Street', 'country' => 'Ireland'],
            'total_tags' => 15,
            'summary' => [
                'tags' => [
                    ['clo_id' => $cloId1, 'category_id' => $category->id, 'object_id' => $obj1->id, 'type_id' => null, 'quantity' => 5, 'materials' => [$material->id], 'brands' => [$brand->id => 2], 'custom_tags' => []],
                    ['clo_id' => $cloId2, 'category_id' => $category->id, 'object_id' => $obj2->id, 'type_id' => null, 'quantity' => 10, 'materials' => [], 'brands' => (object) [], 'custom_tags' => []],
                ],
                'totals' => ['litter' => 15, 'materials' => 5, 'brands' => 2, 'custom_tags' => 0],
                'keys' => ['brands' => [(string) $brand->id => 'test_brand_export']],
            ],
        ]);

        // Create photo_tags so the pre-scan finds columns
        $pt1 = PhotoTag::create(['photo_id' => $photo->id, 'category_id' => $category->id, 'litter_object_id' => $obj1->id, 'category_litter_object_id' => $cloId1, 'quantity' => 5]);
        PhotoTagExtraTags::create(['photo_tag_id' => $pt1->id, 'tag_type' => 'material', 'tag_type_id' => $material->id, 'quantity' => 1]);
        PhotoTagExtraTags::create(['photo_tag_id' => $pt1->id, 'tag_type' => 'brand', 'tag_type_id' => $brand->id, 'quantity' => 2]);
        PhotoTag::create(['photo_id' => $photo->id, 'category_id' => $category->id, 'litter_object_id' => $obj2->id, 'category_litter_object_id' => $cloId2, 'quantity' => 10]);

        // Add custom tags
        $customTag1 = CustomTagNew::firstOrCreate(['key' => 'my_custom_1']);
        $customTag2 = CustomTagNew::firstOrCreate(['key' => 'my_custom_2']);
        $customTag3 = CustomTagNew::firstOrCreate(['key' => 'my_custom_3']);
        $unclassifiedCloId = $this->getUnclassifiedOtherCloId();
        $ptc1 = PhotoTag::create(['photo_id' => $photo->id, 'category_litter_object_id' => $unclassifiedCloId, 'quantity' => 1]);
        PhotoTagExtraTags::create(['photo_tag_id' => $ptc1->id, 'tag_type' => 'custom_tag', 'tag_type_id' => $customTag1->id, 'quantity' => 1]);
        $ptc2 = PhotoTag::create(['photo_id' => $photo->id, 'category_litter_object_id' => $unclassifiedCloId, 'quantity' => 1]);
        PhotoTagExtraTags::create(['photo_tag_id' => $ptc2->id, 'tag_type' => 'custom_tag', 'tag_type_id' => $customTag2->id, 'quantity' => 1]);
        $ptc3 = PhotoTag::create(['photo_id' => $photo->id, 'category_litter_object_id' => $unclassifiedCloId, 'quantity' => 1]);
        PhotoTagExtraTags::create(['photo_tag_id' => $ptc3->id, 'tag_type' => 'custom_tag', 'tag_type_id' => $customTag3->id, 'quantity' => 1]);

        $export = new CreateCSVExport(null, null, null, $user->id);
        $mapped = $export->map($photo->fresh());
        $headings = $export->headings();

        // Fixed columns
        $this->assertEquals($photo->id, $mapped[0]);
        $this->assertEquals(2, $mapped[1]); // verified->value
        $this->assertEquals('No', $mapped[7]); // picked_up = false (remaining=true)
        $this->assertEquals(15, $mapped[9]); // total_tags

        // Object quantities in correct columns
        $obj1Index = array_search($obj1->key, $headings);
        $obj2Index = array_search($obj2->key, $headings);
        $this->assertEquals(5, $mapped[$obj1Index]);
        $this->assertEquals(10, $mapped[$obj2Index]);

        // Material in correct column
        $matIndex = array_search($material->key, $headings);
        $this->assertEquals(5, $mapped[$matIndex]); // inherits parent tag qty

        // Brands
        $brandsIndex = array_search('brands', $headings);
        $this->assertEquals('test_brand_export:2', $mapped[$brandsIndex]);

        // Custom tags
        $ct1Index = array_search('custom_tag_1', $headings);
        $this->assertEquals('my_custom_1', $mapped[$ct1Index]);
        $this->assertEquals('my_custom_2', $mapped[$ct1Index + 1]);
        $this->assertEquals('my_custom_3', $mapped[$ct1Index + 2]);
    }

    public function test_it_maps_to_null_values_for_empty_tags()
    {
        $category = Category::with(['litterObjects' => fn ($q) => $q->orderBy('litter_objects.id')])
            ->orderBy('id')
            ->get()
            ->first(fn ($c) => $c->litterObjects->count() >= 1);
        $obj = $category->litterObjects->first();
        $cloId = CategoryObject::where('category_id', $category->id)->where('litter_object_id', $obj->id)->value('id');

        // Photo with empty summary but has a photo_tag (so category appears in pre-scan)
        $user = User::factory()->create();
        $photo = Photo::factory()->create([
            'verified' => 2,
            'user_id' => $user->id,
            'model' => 'Test',
            'datetime' => now()->toDateTimeString(),
            'lat' => 42.0,
            'lon' => 42.0,
            'remaining' => true,
            'address_array' => ['country' => 'Ireland'],
            'summary' => ['tags' => [], 'totals' => ['litter' => 0, 'materials' => 0, 'brands' => 0, 'custom_tags' => 0]],
        ]);
        PhotoTag::create(['photo_id' => $photo->id, 'category_id' => $category->id, 'litter_object_id' => $obj->id, 'category_litter_object_id' => $cloId, 'quantity' => 0]);

        $export = new CreateCSVExport(null, null, null, $user->id);
        $mapped = $export->map($photo->fresh());
        $headings = $export->headings();

        // Object column should be null (summary has no tags)
        $objIndex = array_search($obj->key, $headings);
        $this->assertNull($mapped[$objIndex]);

        // No materials/types/brands/custom_tags sections
        $this->assertNotContains('MATERIALS', $headings);
        $this->assertNotContains('TYPES', $headings);
        $this->assertNotContains('brands', $headings);
        $this->assertNotContains('custom_tag_1', $headings);
    }

    public function test_materials_are_aggregated_across_multiple_tags()
    {
        $category = Category::with(['litterObjects' => fn ($q) => $q->orderBy('litter_objects.id')])
            ->orderBy('id')
            ->get()
            ->first(fn ($c) => $c->litterObjects->count() >= 2);
        $obj1 = $category->litterObjects[0];
        $obj2 = $category->litterObjects[1];

        $cloId1 = CategoryObject::where('category_id', $category->id)->where('litter_object_id', $obj1->id)->value('id');
        $cloId2 = CategoryObject::where('category_id', $category->id)->where('litter_object_id', $obj2->id)->value('id');

        $material = Materials::orderBy('id')->first();

        $user = User::factory()->create();
        $photo = Photo::factory()->create([
            'verified' => 2,
            'user_id' => $user->id,
            'datetime' => now()->toDateTimeString(),
            'lat' => 42.0,
            'lon' => 42.0,
            'address_array' => ['country' => 'Ireland'],
            'summary' => [
                'tags' => [
                    ['clo_id' => $cloId1, 'category_id' => $category->id, 'object_id' => $obj1->id, 'type_id' => null, 'quantity' => 3, 'materials' => [$material->id], 'brands' => (object) [], 'custom_tags' => []],
                    ['clo_id' => $cloId2, 'category_id' => $category->id, 'object_id' => $obj2->id, 'type_id' => null, 'quantity' => 7, 'materials' => [$material->id], 'brands' => (object) [], 'custom_tags' => []],
                ],
                'totals' => ['litter' => 10, 'materials' => 10, 'brands' => 0, 'custom_tags' => 0],
            ],
        ]);

        // Photo tags for pre-scan
        $pt1 = PhotoTag::create(['photo_id' => $photo->id, 'category_id' => $category->id, 'litter_object_id' => $obj1->id, 'category_litter_object_id' => $cloId1, 'quantity' => 3]);
        PhotoTagExtraTags::create(['photo_tag_id' => $pt1->id, 'tag_type' => 'material', 'tag_type_id' => $material->id, 'quantity' => 1]);
        $pt2 = PhotoTag::create(['photo_id' => $photo->id, 'category_id' => $category->id, 'litter_object_id' => $obj2->id, 'category_litter_object_id' => $cloId2, 'quantity' => 7]);
        PhotoTagExtraTags::create(['photo_tag_id' => $pt2->id, 'tag_type' => 'material', 'tag_type_id' => $material->id, 'quantity' => 1]);

        $export = new CreateCSVExport(null, null, null, $user->id);
        $mapped = $export->map($photo->fresh());
        $headings = $export->headings();

        $materialsHeaderIndex = array_search('MATERIALS', $headings);
        $this->assertNotFalse($materialsHeaderIndex);

        $materialIndex = null;
        for ($i = $materialsHeaderIndex + 1; $i < count($headings); $i++) {
            if ($headings[$i] === $material->key) {
                $materialIndex = $i;
                break;
            }
        }

        $this->assertNotNull($materialIndex);
        $this->assertEquals(10, $mapped[$materialIndex]); // 3 + 7
    }

    public function test_brands_formatted_as_delimited_string()
    {
        $category = Category::with(['litterObjects' => fn ($q) => $q->orderBy('litter_objects.id')])
            ->orderBy('id')
            ->get()
            ->first(fn ($c) => $c->litterObjects->count() >= 1);
        $obj = $category->litterObjects->first();
        $cloId = CategoryObject::where('category_id', $category->id)->where('litter_object_id', $obj->id)->value('id');

        $brand1 = BrandList::firstOrCreate(['key' => 'test_brand_1']);
        $brand2 = BrandList::firstOrCreate(['key' => 'test_brand_2']);

        $user = User::factory()->create();
        $photo = Photo::factory()->create([
            'verified' => 2,
            'user_id' => $user->id,
            'datetime' => now()->toDateTimeString(),
            'lat' => 42.0,
            'lon' => 42.0,
            'address_array' => ['country' => 'Ireland'],
            'summary' => [
                'tags' => [
                    ['clo_id' => $cloId, 'category_id' => $category->id, 'object_id' => $obj->id, 'type_id' => null, 'quantity' => 5, 'materials' => [], 'brands' => [(string) $brand1->id => 1, (string) $brand2->id => 3], 'custom_tags' => []],
                ],
                'totals' => ['litter' => 5, 'materials' => 0, 'brands' => 4, 'custom_tags' => 0],
                'keys' => ['brands' => [(string) $brand1->id => 'test_brand_1', (string) $brand2->id => 'test_brand_2']],
            ],
        ]);

        $pt = PhotoTag::create(['photo_id' => $photo->id, 'category_id' => $category->id, 'litter_object_id' => $obj->id, 'category_litter_object_id' => $cloId, 'quantity' => 5]);
        PhotoTagExtraTags::create(['photo_tag_id' => $pt->id, 'tag_type' => 'brand', 'tag_type_id' => $brand1->id, 'quantity' => 1]);
        PhotoTagExtraTags::create(['photo_tag_id' => $pt->id, 'tag_type' => 'brand', 'tag_type_id' => $brand2->id, 'quantity' => 3]);

        $export = new CreateCSVExport(null, null, null, $user->id);
        $mapped = $export->map($photo->fresh());
        $headings = $export->headings();

        $brandsIndex = array_search('brands', $headings);
        $this->assertNotFalse($brandsIndex);

        $brandsValue = $mapped[$brandsIndex];
        $this->assertStringContainsString('test_brand_1:1', $brandsValue);
        $this->assertStringContainsString('test_brand_2:3', $brandsValue);
        $this->assertStringContainsString(';', $brandsValue);
    }

    public function test_types_are_mapped_from_summary()
    {
        $category = Category::with(['litterObjects' => fn ($q) => $q->orderBy('litter_objects.id')])
            ->orderBy('id')
            ->get()
            ->first(fn ($c) => $c->litterObjects->count() >= 1);
        $obj = $category->litterObjects->first();
        $cloId = CategoryObject::where('category_id', $category->id)->where('litter_object_id', $obj->id)->value('id');

        $type = LitterObjectType::orderBy('id')->first();

        $user = User::factory()->create();
        $photo = Photo::factory()->create([
            'verified' => 2,
            'user_id' => $user->id,
            'datetime' => now()->toDateTimeString(),
            'lat' => 42.0,
            'lon' => 42.0,
            'address_array' => ['country' => 'Ireland'],
            'summary' => [
                'tags' => [
                    ['clo_id' => $cloId, 'category_id' => $category->id, 'object_id' => $obj->id, 'type_id' => $type->id, 'quantity' => 8, 'materials' => [], 'brands' => (object) [], 'custom_tags' => []],
                ],
                'totals' => ['litter' => 8, 'materials' => 0, 'brands' => 0, 'custom_tags' => 0],
            ],
        ]);

        PhotoTag::create([
            'photo_id' => $photo->id,
            'category_id' => $category->id,
            'litter_object_id' => $obj->id,
            'category_litter_object_id' => $cloId,
            'litter_object_type_id' => $type->id,
            'quantity' => 8,
        ]);

        $export = new CreateCSVExport(null, null, null, $user->id);
        $mapped = $export->map($photo->fresh());
        $headings = $export->headings();

        $typesHeaderIndex = array_search('TYPES', $headings);
        $this->assertNotFalse($typesHeaderIndex);

        $typeIndex = null;
        for ($i = $typesHeaderIndex + 1; $i < count($headings); $i++) {
            if ($headings[$i] === $type->key) {
                $typeIndex = $i;
                break;
            }
        }

        $this->assertNotNull($typeIndex);
        $this->assertEquals(8, $mapped[$typeIndex]);
    }
}
