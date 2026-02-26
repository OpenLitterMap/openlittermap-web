<?php

namespace Tests\Unit\Exports;

use App\Exports\CreateCSVExport;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CategoryObject;
use App\Models\Litter\Tags\CustomTagNew;
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
        $expected = ['id', 'verification', 'phone', 'date_taken', 'date_uploaded', 'lat', 'lon', 'picked up', 'address', 'total_litter'];

        $categories = Category::with(['litterObjects' => fn ($q) => $q->orderBy('litter_objects.id')])->orderBy('id')->get();
        foreach ($categories as $category) {
            $expected[] = strtoupper($category->key);
            foreach ($category->litterObjects as $object) {
                $expected[] = $object->key;
            }
        }
        $expected = array_merge($expected, ['custom_tag_1', 'custom_tag_2', 'custom_tag_3']);

        $this->assertDatabaseCount('photos', 0);

        $export = new CreateCSVExport('null', 1, null, null);

        $this->assertEquals($expected, $export->headings());
        $this->assertDatabaseCount('photos', 0);
    }

    public function test_it_has_correct_mappings_for_all_categories_and_tags()
    {
        // Pick the first category and its first two objects
        $category = Category::with(['litterObjects' => fn ($q) => $q->orderBy('litter_objects.id')])->orderBy('id')->first();
        $objects = $category->litterObjects;
        $obj1 = $objects[0];
        $obj2 = $objects[1];

        $cloId1 = CategoryObject::where('category_id', $category->id)->where('litter_object_id', $obj1->id)->value('id');
        $cloId2 = CategoryObject::where('category_id', $category->id)->where('litter_object_id', $obj2->id)->value('id');

        $photo = Photo::factory()->create([
            'verified' => 2,
            'model' => 'Redmi Note 8 pro',
            'datetime' => now()->toDateTimeString(),
            'lat' => 42.0,
            'lon' => 42.0,
            'remaining' => true,
            'address_array' => ['road' => '12345 Street', 'country' => 'Ireland'],
            'total_litter' => 15,
            'summary' => [
                'tags' => [
                    ['clo_id' => $cloId1, 'category_id' => $category->id, 'object_id' => $obj1->id, 'quantity' => 5, 'materials' => [], 'brands' => (object) [], 'custom_tags' => []],
                    ['clo_id' => $cloId2, 'category_id' => $category->id, 'object_id' => $obj2->id, 'quantity' => 10, 'materials' => [], 'brands' => (object) [], 'custom_tags' => []],
                ],
                'totals' => ['litter' => 15, 'materials' => 0, 'brands' => 0, 'custom_tags' => 0],
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
                'tags' => [],
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

        $expected = array_merge($expected, [null, null, null]);

        $export = new CreateCSVExport('null', 1, null, null);

        $this->assertEquals($expected, $export->map($photo->fresh()));
    }
}
