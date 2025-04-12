<?php

namespace Tests\Feature\Migration;

use App\Models\Litter\Tags\CustomTagNew;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Litter\Tags\PhotoTagExtraTags;
use App\Models\Litter\Categories\Smoking;
use App\Models\Litter\Categories\Alcohol;
use App\Models\Photo;
use App\Services\Tags\UpdateTagsService;
use Database\Seeders\Tags\GenerateTagsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateTagsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected UpdateTagsService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GenerateTagsSeeder::class);
        $this->service = app(UpdateTagsService::class);
    }

    /** @test */
    public function it_migrates_photo_tags_and_primary_custom_tag()
    {
        $smoking = Smoking::create(['butts' => 3]);
        $photo = Photo::factory()->create([
            'smoking_id' => $smoking->id,
            'remaining' => 0,
        ]);

        $photo->customTags()->createMany([
            ['tag' => 'random_litter'],
            ['tag' => 'party_waste'],
        ]);

        $this->service->updateTags($photo);

        $this->assertDatabaseHas('photo_tags', [
            'photo_id' => $photo->id,
            'quantity' => 3,
        ]);

        foreach (['random_litter', 'party_waste'] as $customTag) {
            $this->assertDatabaseHas('custom_tags_new', ['key' => $customTag]);
        }
    }

    /** @test */
    public function it_handles_photos_with_no_legacy_tags()
    {
        $photo = Photo::factory()->create(['remaining' => 0]);

        $this->service->updateTags($photo);

        $this->assertDatabaseMissing('photo_tags', [
            'photo_id' => $photo->id,
        ]);
    }

    /** @test */
    public function it_creates_photo_tags_for_each_object()
    {
        $alcohol = Alcohol::create([
            'beerCan' => 2,
            'wineBottle' => 1,
        ]);
        $photo = Photo::factory()->create([
            'alcohol_id' => $alcohol->id,
            'remaining' => 0,
        ]);

        $this->service->updateTags($photo);

        $this->assertCount(2, PhotoTag::where('photo_id', $photo->id)->get());
    }

    /** @test */
    public function it_attaches_materials_as_extra_tags()
    {
        $alcohol = Alcohol::create(['beer_bottle' => 1]);
        $photo = Photo::factory()->create([
            'alcohol_id' => $alcohol->id,
            'remaining' => 0,
        ]);

        $this->service->updateTags($photo);

        $photoTag = PhotoTag::where('photo_id', $photo->id)->first();

        $this->assertDatabaseHas('photo_tag_extra_tags', [
            'photo_tag_id' => $photoTag->id,
            'tag_type' => 'material',
        ]);
    }

    /** @test */
    public function it_creates_single_photo_tag_when_only_custom_tag_exists()
    {
        $photo = Photo::factory()->create(['remaining' => 0]);
        $photo->customTags()->create(['tag' => 'illegal_dumping']);

        $this->service->updateTags($photo);

        $photoTag = PhotoTag::where('photo_id', $photo->id)->first();

        $this->assertNotNull($photoTag);
        $this->assertNotNull($photoTag->custom_tag_primary_id);
        $this->assertDatabaseHas('custom_tags_new', ['key' => 'illegal_dumping']);
    }

    /** @test */
    public function it_attaches_custom_tags_as_extras_when_objects_exist()
    {
        $alcohol = Alcohol::create(['beerCan' => 1]);
        $photo = Photo::factory()->create([
            'alcohol_id' => $alcohol->id,
        ]);
        $photo->customTags()->create(['tag' => 'camping']);

        $this->service->updateTags($photo);

        $photoTag = PhotoTag::where('photo_id', $photo->id)->first();

        $this->assertDatabaseHas('photo_tag_extra_tags', [
            'photo_tag_id' => $photoTag->id,
            'tag_type' => 'custom_tag',
        ]);
    }

    /** @test */
    public function it_skips_invalid_category_or_object()
    {
        $photo = Photo::factory()->create();
        $photo->update(['tags' => ['alien_category' => ['unknown_object' => 2]]]);

        $this->service->updateTags($photo);

        $this->assertDatabaseMissing('photo_tags', ['photo_id' => $photo->id]);
    }

    /** @test */
    public function it_does_not_duplicate_category_object_relationships()
    {
        $smoking = Smoking::create(['skins' => 1]);
        $photo = Photo::factory()->create([
            'smoking_id' => $smoking->id,
        ]);

        $this->service->updateTags($photo);
        $this->assertDatabaseCount('category_litter_object', 1);

        $this->service->updateTags($photo);
        $this->assertDatabaseCount('category_litter_object', 1);
    }
}
