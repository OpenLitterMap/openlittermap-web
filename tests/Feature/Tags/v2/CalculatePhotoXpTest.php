<?php

namespace Tests\Feature\Tags\v2;

use App\Enums\CategoryKey;
use App\Models\Litter\Categories\Smoking;
use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CustomTagNew;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\Materials;
use App\Models\Litter\Tags\PhotoTagExtraTags;
use App\Models\Photo;
use App\Services\Tags\UpdateTagsService;
use Database\Seeders\Tags\GenerateBrandsSeeder;
use Database\Seeders\Tags\GenerateTagsSeeder;
use Tests\TestCase;

class CalculatePhotoXpTest extends TestCase
{
    protected UpdateTagsService $tagsService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([GenerateTagsSeeder::class, GenerateBrandsSeeder::class]);
        $this->tagsService = app(UpdateTagsService::class);
    }

    /** @test */
    public function empty_summary_awards_zero_xp()
    {
        $photo = Photo::factory()->create([
            'remaining' => 1, // Not picked up, so no pickup bonus
        ]);
        $this->assertNull($photo->xp);

        $photo->generateSummary();
        $photo->refresh();

        // no tags means total XP = 0 (upload XP is tracked separately on user, not photo)
        $this->assertSame(0, $photo->xp);
    }

    /** @test */
    public function simple_object_and_extra_tags_result_in_weighted_xp()
    {
        $smoking = Smoking::create(['butts' => 2]);
        $photo   = Photo::factory()->create([
            'smoking_id' => $smoking->id,
            'remaining'  => 0, // Picked up
        ]);

        // migrate legacy tags into photo_tags
        $this->tagsService->updateTags($photo);

        // add an extra material + brand
        $pt = $photo->photoTags()->first();
        $pt->extraTags()->create([
            'tag_type'    => 'material',
            'tag_type_id' => Materials::first()->id,
            'quantity'    => 1,
        ]);
        $pt->extraTags()->create([
            'tag_type'    => 'brand',
            'tag_type_id' => BrandList::first()->id,
            'quantity'    => 1,
        ]);

        $photo->generateSummary();
        $photo->refresh();

        // XP calculation:
        // Objects (butts, qty=2): 2 × 1 = 2
        // 3 materials (2 from deprecated mapping: plastic,paper + 1 explicit) × parent qty 2 × 2 = 12
        // Brand: 1 × 3 = 3 (independent)
        // Picked up: 5
        $this->assertSame(22, $photo->xp);
    }

    /** @test */
    public function dumping_and_bags_litter_override_object_xp()
    {
        $industrialCat = Category::firstOrCreate(['key' => CategoryKey::Industrial->value]);
        $unclassifiedCat = Category::firstOrCreate(['key' => CategoryKey::Unclassified->value]);

        $specialObjects = [
            ['key' => 'dumping_small', 'xp' => 10, 'category' => $industrialCat],
            ['key' => 'dumping_medium', 'xp' => 25, 'category' => $industrialCat],
            ['key' => 'dumping_large', 'xp' => 50, 'category' => $industrialCat],
            ['key' => 'bags_litter', 'xp' => 10, 'category' => $unclassifiedCat],
        ];

        foreach ($specialObjects as $spec) {
            $key = $spec['key'];
            $xpPerUnit = $spec['xp'];
            $category = $spec['category'];
            $object = LitterObject::firstOrCreate(['key' => $key]);

            $photo = Photo::factory()->create([
                'remaining' => 1,
            ]);

            $photo->photoTags()->create([
                'category_litter_object_id' => $this->getCloId($category->id, $object->id),
                'litter_object_id' => $object->id,
                'quantity'         => 2,
                'category_id'      => $category->id,
            ]);

            $photo->generateSummary();
            $photo->refresh();

            $this->assertSame(
                2 * $xpPerUnit,
                $photo->xp,
                "XP for object key '{$key}' should be 2×{$xpPerUnit}"
            );
        }
    }

    /** @test */
    public function multiple_objects_in_same_category_are_sorted_desc()
    {
        $category = Category::where('key', CategoryKey::Smoking->value)->firstOrFail();
        $buttsObj = LitterObject::where('key', 'butts')->firstOrFail();
        $cigarObj = LitterObject::firstOrCreate(['key' => 'cigar']);

        $photo = Photo::factory()->create();

        $photo->photoTags()->create([
            'category_litter_object_id' => $this->getCloId($category->id, $buttsObj->id),
            'litter_object_id' => $buttsObj->id,
            'quantity'         => 1,
            'category_id'      => $category->id,
        ]);
        $photo->photoTags()->create([
            'category_litter_object_id' => $this->getCloId($category->id, $cigarObj->id),
            'litter_object_id' => $cigarObj->id,
            'quantity'         => 3,
            'category_id'      => $category->id,
        ]);

        $photo->generateSummary();
        $photo->refresh();

        // Flat format: tags is a list. Verify total litter.
        $this->assertEquals(4, $photo->summary['totals']['litter']);

        // Verify both tags are present
        $tagQuantities = array_column($photo->summary['tags'], 'quantity');
        sort($tagQuantities);
        $this->assertEquals([1, 3], $tagQuantities);
    }

    /** @test */
    public function regenerate_summary_resets_xp()
    {
        $smoking = Smoking::create(['butts' => 2]);
        $photo   = Photo::factory()->create([
            'smoking_id' => $smoking->id,
            'remaining'  => 0,
        ]);

        $this->tagsService->updateTags($photo);

        $photo->generateSummary();
        $firstXp = $photo->xp;

        $photo->update(['summary' => ['foo' => 'bar'], 'xp' => 999]);
        $photo->refresh();

        $photo->generateSummary();
        $photo->refresh();

        $this->assertNotSame(999, $photo->xp);
        $this->assertSame($firstXp, $photo->xp);
    }

    /** @test */
    public function extra_tags_without_object_are_counted_but_not_as_objects()
    {
        $photo = Photo::factory()->create([
            'remaining' => 1,
        ]);
        $pt = $photo->photoTags()->create([
            'category_litter_object_id' => $this->getUnclassifiedOtherCloId(),
            'litter_object_id' => null,
            'quantity'         => 0,
            'category_id'      => null,
        ]);
        $pt->extraTags()->create([
            'tag_type'    => 'custom_tag',
            'tag_type_id' => CustomTagNew::factory()->create(['key' => 'x'])->id,
            'quantity'    => 2,
        ]);

        $photo->generateSummary();
        $photo->refresh();

        // In flat format: litter is total qty, custom_tags counted separately
        $this->assertSame(0, $photo->summary['totals']['litter']);
        $this->assertSame(0, $photo->summary['totals']['custom_tags']); // qty=0 means no weighting

        // XP should be 0: no object XP because parent qty=0 means custom_tag contributes 0
        $this->assertSame(0, $photo->xp);
    }

    /** @test */
    public function photos_with_multiple_categories_are_sorted_by_total_quantity()
    {
        $photo = Photo::factory()->create(['remaining' => 1]);

        $smokingCat = Category::where('key', CategoryKey::Smoking->value)->firstOrFail();
        $foodCat = Category::where('key', CategoryKey::Food->value)->firstOrFail();
        $wrapperObj = LitterObject::where('key', 'wrapper')->first();
        $buttsObj = LitterObject::where('key', 'butts')->first();

        $photo->photoTags()->create([
            'category_litter_object_id' => $this->getCloId($foodCat->id, $wrapperObj->id),
            'category_id' => $foodCat->id,
            'litter_object_id' => $wrapperObj->id,
            'quantity' => 3,
        ]);

        $photo->photoTags()->create([
            'category_litter_object_id' => $this->getCloId($smokingCat->id, $buttsObj->id),
            'category_id' => $smokingCat->id,
            'litter_object_id' => $buttsObj->id,
            'quantity' => 5,
        ]);

        $photo->generateSummary();
        $photo->refresh();

        // Verify totals
        $this->assertEquals(8, $photo->summary['totals']['litter']);

        // XP: 8 (objects)
        $this->assertEquals(8, $photo->xp);
    }

    /** @test */
    public function brands_only_photo_creates_special_category()
    {
        $photo = Photo::factory()->create(['remaining' => 1]);

        $brand1 = BrandList::first();
        $brand2 = BrandList::skip(1)->first();

        // Create a brands-only photo tag
        $pt = $photo->photoTags()->create([
            'category_litter_object_id' => $this->getUnclassifiedOtherCloId(),
            'category_id' => null,
            'litter_object_id' => null,
            'quantity' => 5,
        ]);

        $pt->extraTags()->create([
            'tag_type' => 'brand',
            'tag_type_id' => $brand1->id,
            'quantity' => 3,
        ]);

        $pt->extraTags()->create([
            'tag_type' => 'brand',
            'tag_type_id' => $brand2->id,
            'quantity' => 2,
        ]);

        $photo->generateSummary();
        $photo->refresh();

        $this->assertEquals(5, $photo->summary['totals']['brands']);
        // No object_id set, so litter count is 0 (only objects count as litter)
        $this->assertEquals(0, $photo->summary['totals']['litter']);

        // XP: 0 (no objects) + 5×3 (brands) = 15
        $this->assertEquals(15, $photo->xp);
    }

    /** @test */
    public function primary_custom_tag_is_counted_correctly()
    {
        $photo = Photo::factory()->create(['remaining' => 0]);
        $customTag = CustomTagNew::factory()->create(['key' => 'test_custom']);

        $pt = $photo->photoTags()->create([
            'category_litter_object_id' => $this->getUnclassifiedOtherCloId(),
            'quantity' => 3,
            'picked_up' => true,
        ]);
        PhotoTagExtraTags::create([
            'photo_tag_id' => $pt->id,
            'tag_type' => 'custom_tag',
            'tag_type_id' => $customTag->id,
            'quantity' => 1,
        ]);

        $photo->generateSummary();
        $photo->refresh();

        $this->assertEquals(3, $photo->summary['totals']['custom_tags']);
        // No object_id set, so litter count is 0 (only objects count as litter)
        $this->assertEquals(0, $photo->summary['totals']['litter']);

        // XP: 0 (no objects) + 3×1 (custom tags) + 5 (picked up) = 8
        $this->assertEquals(8, $photo->xp);
    }

    /** @test */
    public function materials_attached_to_objects_count_properly()
    {
        $photo = Photo::factory()->create(['remaining' => 1]);

        $plastic = Materials::where('key', 'plastic')->first();
        $glass = Materials::where('key', 'glass')->first();
        $bottleObj = LitterObject::where('key', 'bottle')->first();
        $softdrinksCat = Category::where('key', CategoryKey::Softdrinks->value)->first();

        $pt = $photo->photoTags()->create([
            'category_litter_object_id' => $this->getCloId($softdrinksCat->id, $bottleObj->id),
            'litter_object_id' => $bottleObj->id,
            'quantity' => 2,
            'category_id' => $softdrinksCat->id,
        ]);

        $pt->extraTags()->create([
            'tag_type' => 'material',
            'tag_type_id' => $plastic->id,
            'quantity' => 1,
        ]);

        $pt->extraTags()->create([
            'tag_type' => 'material',
            'tag_type_id' => $glass->id,
            'quantity' => 1,
        ]);

        $photo->generateSummary();
        $photo->refresh();

        // Materials weighted by parent qty: 2 materials × 2 parent qty = 4
        $this->assertEquals(4, $photo->summary['totals']['materials']);
        $this->assertEquals(2, $photo->summary['totals']['litter']);

        // XP: 2 (objects) + 4×2 (materials) = 10
        $this->assertEquals(10, $photo->xp);
    }

    /** @test */
    public function zero_quantity_items_are_filtered_out()
    {
        $photo = Photo::factory()->create(['remaining' => 1]);
        $firstObj = LitterObject::first();
        $firstCat = Category::first();

        $pt = $photo->photoTags()->create([
            'category_litter_object_id' => $this->getCloId($firstCat->id, $firstObj->id),
            'litter_object_id' => $firstObj->id,
            'quantity' => 0,
            'category_id' => $firstCat->id,
        ]);

        $pt->extraTags()->create([
            'tag_type' => 'brand',
            'tag_type_id' => BrandList::first()->id,
            'quantity' => 2,
        ]);

        $photo->generateSummary();
        $photo->refresh();

        $this->assertEquals(0, $photo->summary['totals']['litter']);
        $this->assertEquals(2, $photo->summary['totals']['brands']);

        // XP: 0 (no objects) + 2×3 (brands) = 6
        $this->assertEquals(6, $photo->xp);
    }

    /** @test */
    public function duplicate_brands_across_objects_sum_correctly()
    {
        $photo = Photo::factory()->create(['remaining' => 1]);

        $brand = BrandList::first();
        $category = Category::where('key', CategoryKey::Smoking->value)->first();
        $buttsObj = LitterObject::where('key', 'butts')->first();
        $packagingObj = LitterObject::where('key', 'packaging')->first();

        $pt1 = $photo->photoTags()->create([
            'category_litter_object_id' => $this->getCloId($category->id, $buttsObj->id),
            'litter_object_id' => $buttsObj->id,
            'quantity' => 2,
            'category_id' => $category->id,
        ]);

        $pt1->extraTags()->create([
            'tag_type' => 'brand',
            'tag_type_id' => $brand->id,
            'quantity' => 2,
        ]);

        $pt2 = $photo->photoTags()->create([
            'category_litter_object_id' => $this->getCloId($category->id, $packagingObj->id),
            'litter_object_id' => $packagingObj->id,
            'quantity' => 1,
            'category_id' => $category->id,
        ]);

        $pt2->extraTags()->create([
            'tag_type' => 'brand',
            'tag_type_id' => $brand->id,
            'quantity' => 1,
        ]);

        $photo->generateSummary();
        $photo->refresh();

        $this->assertEquals(3, $photo->summary['totals']['brands']);
        $this->assertEquals(3, $photo->summary['totals']['litter']);

        // XP: 3 (objects) + 3×3 (brands) = 12
        $this->assertEquals(12, $photo->xp);
    }

    /** @test */
    public function picked_up_bonus_only_applies_when_remaining_is_zero()
    {
        $firstObj = LitterObject::first();
        $firstCat = Category::first();
        $cloId = $this->getCloId($firstCat->id, $firstObj->id);

        // Test with remaining = 0 (picked up)
        $photo1 = Photo::factory()->create(['remaining' => 0]);
        $photo1->photoTags()->create([
            'category_litter_object_id' => $cloId,
            'litter_object_id' => $firstObj->id,
            'quantity' => 1,
            'category_id' => $firstCat->id,
            'picked_up' => true,
        ]);

        $photo1->generateSummary();
        $photo1->refresh();

        // XP: 1 (object) + 5 (picked up) = 6
        $this->assertEquals(6, $photo1->xp);

        // Test with remaining = 1 (not picked up)
        $photo2 = Photo::factory()->create(['remaining' => 1]);
        $photo2->photoTags()->create([
            'category_litter_object_id' => $cloId,
            'litter_object_id' => $firstObj->id,
            'quantity' => 1,
            'category_id' => $firstCat->id,
            'picked_up' => false,
        ]);

        $photo2->generateSummary();
        $photo2->refresh();

        // XP: 1 (object) = 1 (no picked up bonus)
        $this->assertEquals(1, $photo2->xp);
    }

    /** @test */
    public function complex_photo_with_all_tag_types_calculates_correctly()
    {
        $photo = Photo::factory()->create(['remaining' => 0]);

        $bottleObj = LitterObject::where('key', 'bottle')->first();
        $softdrinksCat = Category::where('key', CategoryKey::Softdrinks->value)->first();
        $industrialCat = Category::where('key', CategoryKey::Industrial->value)->first();
        $largeObj = LitterObject::firstOrCreate(['key' => 'dumping_large']);

        // Add regular object with material and brand
        $pt1 = $photo->photoTags()->create([
            'category_litter_object_id' => $this->getCloId($softdrinksCat->id, $bottleObj->id),
            'litter_object_id' => $bottleObj->id,
            'quantity' => 3,
            'category_id' => $softdrinksCat->id,
        ]);

        $pt1->extraTags()->create([
            'tag_type' => 'material',
            'tag_type_id' => Materials::where('key', 'plastic')->first()->id,
            'quantity' => 3,
        ]);

        $pt1->extraTags()->create([
            'tag_type' => 'brand',
            'tag_type_id' => BrandList::first()->id,
            'quantity' => 2,
        ]);

        $pt1->extraTags()->create([
            'tag_type' => 'custom_tag',
            'tag_type_id' => CustomTagNew::factory()->create(['key' => 'broken'])->id,
            'quantity' => 1,
        ]);

        // Add special object (large)
        $pt2 = $photo->photoTags()->create([
            'category_litter_object_id' => $this->getCloId($industrialCat->id, $largeObj->id),
            'litter_object_id' => $largeObj->id,
            'quantity' => 1,
            'category_id' => $industrialCat->id,
        ]);

        $photo->generateSummary();
        $photo->refresh();

        // Verify totals (flat format)
        $this->assertEquals(4, $photo->summary['totals']['litter']); // 3 bottles + 1 large
        $this->assertEquals(3, $photo->summary['totals']['materials']); // weighted: 1 material × 3 parent qty
        $this->assertEquals(2, $photo->summary['totals']['brands']);
        $this->assertEquals(3, $photo->summary['totals']['custom_tags']); // weighted: 1 custom_tag × 3 parent qty

        // XP calculation:
        // 3×1 (bottles) + 1×50 (large item)
        // + 3×2 (materials)
        // + 2×3 (brands)
        // + 3×1 (custom tag)
        // + 5 (picked up)
        // = 3 + 50 + 6 + 6 + 3 + 5 = 73
        $this->assertEquals(73, $photo->xp);
    }
}
