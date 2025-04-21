<?php

namespace Tests\Feature\Tags\v2;

use App\Models\Litter\Categories\Smoking;
use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CustomTagNew;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\Materials;
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
        // seed your categories/objects/tags so UpdateTagsService can work
        $this->seed([GenerateTagsSeeder::class, GenerateBrandsSeeder::class]);

        $this->tagsService = app(UpdateTagsService::class);
    }

    /** @test */
    public function empty_summary_still_awards_only_upload_xp()
    {
        $photo = Photo::factory()->create();
        $this->assertNull($photo->xp);

        $photo->generateSummary();
        $photo->refresh();

        // upload XP is 5, no tags means total XP = 5
        $this->assertSame(5, $photo->xp);
    }

    /** @test */
    public function simple_object_and_extra_tags_result_in_weighted_xp()
    {
        $smoking = Smoking::create(['butts' => 2]);
        $photo   = Photo::factory()->create([
            'smoking_id' => $smoking->id,
            'remaining'  => 0,
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

        $this->assertSame(12, $photo->xp);
    }

    /** @test */
    public function small_medium_large_and_bagsLitter_override_object_xp()
    {
        foreach (['small' => 10, 'medium' => 25, 'large' => 50, 'bagsLitter' => 10] as $key => $xpPerUnit) {
            $category = Category::firstOrCreate(['key' => 'dumping']);
            $object   = LitterObject::where('key', $key)->firstOrFail();
            $photo    = Photo::factory()->create();

            $photo->photoTags()->create([
                'litter_object_id' => $object->id,
                'quantity'         => 2,
                'category_id'      => $category->id,
            ]);

            $photo->generateSummary();
            $photo->refresh();

            $this->assertSame(
                5 + 2 * $xpPerUnit,
                $photo->xp,
                "XP for object key '{$key}' should be 5 + 2×{$xpPerUnit}"
            );
        }
    }

    /** @test */
    public function multiple_objects_in_same_category_are_sorted_desc()
    {
        $category = Category::where('key', 'smoking')->firstOrFail();
        $buttsObj = LitterObject::where('key', 'butts')->firstOrFail();
        $cigarObj = LitterObject::factory()->create(['key' => 'cigar']);

        $photo = Photo::factory()->create();

        // create two PhotoTags under the same category
        $photo->photoTags()->create([
            'litter_object_id' => $buttsObj->id,
            'quantity'         => 1,
            'category_id'      => $category->id,
        ]);
        $photo->photoTags()->create([
            'litter_object_id' => $cigarObj->id,
            'quantity'         => 3,
            'category_id'      => $category->id,
        ]);

        $photo->generateSummary();
        $photo->refresh();

        $objects = array_keys($photo->summary['tags']['smoking']);
        $this->assertEquals(['cigar', 'butts'], $objects);
    }

    /** @test */
    public function regenerate_summary_resets_xp()
    {
        $smoking = Smoking::create(['butts' => 2]);
        $photo   = Photo::factory()->create([
            'smoking_id' => $smoking->id,
            'remaining'  => 0,
        ]);
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
        $photo = Photo::factory()->create();
        $pt = $photo->photoTags()->create([
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

        $this->assertSame(0, $photo->summary['totals']['total_objects']);
        $this->assertSame(2, $photo->summary['totals']['custom_tags']);
    }
}
