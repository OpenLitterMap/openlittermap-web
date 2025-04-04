<?php

namespace Tests\Feature\Migration;

use App\Models\Litter\Tags\CustomTagNew;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Litter\Tags\PhotoTagExtraTags;
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
    public function it_migrates_old_tags_and_custom_tags_to_the_new_structure()
    {
        // Arrange: Create a photo using old-style tag structure
        $photo = Photo::factory()->create([
            'tags' => [
                'alcohol' => [
                    'beerBottle' => 2,         // → maps to beer_bottle + glass
                    'wineBottle' => 1,         // → maps to wine_bottle + glass
                ],
                'softdrinks' => [
                    'tinCan' => 3,             // → maps to soda_can + aluminium
                ],
            ],
            'customTags' => [
                'random_litter',
                'party_waste',
            ],
            'remaining' => 0,
        ]);

        // Act
        $this->service->updateTags($photo);

        // Assert: photo_tags created for classified objects
        $this->assertDatabaseHas('photo_tags', [
            'photo_id' => $photo->id,
            'quantity' => 2,
        ]);

        // Assert: extra material tags were created (e.g. glass, aluminium)
        $this->assertGreaterThanOrEqual(
            1,
            PhotoTagExtraTags::where('photo_tag_id', PhotoTag::first()->id)
                ->where('tag_type', 'material')
                ->count()
        );

        // Assert: custom tags are created and linked
        foreach (['random_litter', 'party_waste'] as $customKey) {
            $this->assertDatabaseHas('custom_tags_new', ['key' => $customKey]);
            $this->assertDatabaseHas('photo_tags', [
                'photo_id' => $photo->id,
                'custom_tag_primary_id' => CustomTagNew::where('key', $customKey)->first()->id,
            ]);
        }
    }

    /** @test */
    public function it_skips_empty_tags_gracefully()
    {
        $photo = Photo::factory()->create([
            'tags' => [],
            'customTags' => [],
        ]);

        $this->service->updateTags($photo);

        $this->assertDatabaseMissing('photo_tags', [
            'photo_id' => $photo->id,
        ]);
    }
}
