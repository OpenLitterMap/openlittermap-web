<?php

namespace Tests\Unit\Migration;

use App\Models\Litter\Categories\SoftDrinks;
use App\Models\Litter\Categories\Brand;
use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\Category;
use App\Models\Photo;
use App\Models\Users\User;
use App\Services\Tags\UpdateTagsService;
use Database\Seeders\Tags\GenerateBrandsSeeder;
use Database\Seeders\Tags\GenerateTagsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebugTagRetrievalTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function photo_tags_method_returns_brands_correctly()
    {
        $this->seed([
            GenerateTagsSeeder::class,
            GenerateBrandsSeeder::class
        ]);

        $user = User::factory()->create();
        $photo = Photo::factory()->create(['user_id' => $user->id]);

        // Create old format tags
        $softdrinksRecord = SoftDrinks::create(['tinCan' => 1]);
        $brandsRecord = Brand::create(['coke' => 1]);

        $photo->softdrinks_id = $softdrinksRecord->id;
        $photo->brands_id = $brandsRecord->id;
        $photo->save();
        $photo = $photo->refresh();

        // Test photo->tags() returns brands
        $tags = $photo->tags();

        $this->assertNotEmpty($tags, "Tags should not be empty");
        $this->assertArrayHasKey('brands', $tags, "Tags should have 'brands' key");
        $this->assertArrayHasKey('coke', $tags['brands'], "Brands should have 'coke' key");
        $this->assertEquals(1, $tags['brands']['coke'], "Coke quantity should be 1");
    }

    /** @test */
    public function service_parses_brands_as_global_brands()
    {
        $this->seed([
            GenerateTagsSeeder::class,
            GenerateBrandsSeeder::class
        ]);

        $user = User::factory()->create();
        $photo = Photo::factory()->create(['user_id' => $user->id]);

        // Create old format tags
        $softdrinksRecord = SoftDrinks::create(['tinCan' => 1]);
        $brandsRecord = Brand::create(['coke' => 1]);

        $photo->softdrinks_id = $softdrinksRecord->id;
        $photo->brands_id = $brandsRecord->id;
        $photo->save();
        $photo = $photo->refresh();

        // Test service methods
        $service = app(UpdateTagsService::class);
        [$originalTags, $customTagsOld] = $service->getTags($photo);

        // Verify getTags returns brands
        $this->assertArrayHasKey('brands', $originalTags, "Service should retrieve brands");
        $this->assertEquals(['coke' => 1], $originalTags['brands'], "Should have coke brand");

        // Test parseTags extracts brands to globalBrands
        $parsedTags = $service->parseTags($originalTags, $customTagsOld, $photo->id);

        $this->assertArrayHasKey('globalBrands', $parsedTags, "Should have globalBrands key");
        $this->assertCount(1, $parsedTags['globalBrands'], "Should have 1 global brand");

        $brand = $parsedTags['globalBrands'][0];
        $this->assertEquals('coke', $brand['key'], "Brand key should be 'coke'");
        $this->assertEquals(1, $brand['quantity'], "Brand quantity should be 1");
        $this->assertNotNull($brand['id'], "Brand should have an ID");
    }
}
