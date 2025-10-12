<?php

namespace Tests\Unit\Migration;

use App\Models\Litter\Categories\SoftDrinks;
use App\Models\Litter\Categories\Brand;
use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CategoryObject;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\Taggable;
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
    public function debug_what_tags_are_retrieved_from_photo()
    {
        $this->seed([
            GenerateTagsSeeder::class,
            GenerateBrandsSeeder::class
        ]);

        $user = User::factory()->create();

        // Create photo with old tags
        $photo = Photo::factory()->create(['user_id' => $user->id]);

        // Create old format tags
        $softdrinksRecord = SoftDrinks::create(['tinCan' => 1]);
        $brandsRecord = Brand::create(['coke' => 1]);

        $photo->softdrinks_id = $softdrinksRecord->id;
        $photo->brands_id = $brandsRecord->id;
        $photo->save();
        $photo = $photo->refresh();

        echo "\n=== PHOTO CREATED ===\n";
        echo "Photo ID: {$photo->id}\n";
        echo "SoftDrinks ID: {$photo->softdrinks_id}\n";
        echo "Brands ID: {$photo->brands_id}\n";

        // Check what the photo relationships return
        echo "\n=== CHECKING RELATIONSHIPS ===\n";

        if ($photo->softdrinks) {
            echo "SoftDrinks relationship exists\n";
            echo "  tinCan value: " . $photo->softdrinks->tinCan . "\n";
        } else {
            echo "SoftDrinks relationship is NULL!\n";
        }

        if ($photo->brands) {
            echo "Brands relationship exists\n";
            echo "  coke value: " . $photo->brands->coke . "\n";

            // Check what Brand::types() returns
            $brandTypes = Brand::types();
            echo "  Brand::types() includes: " . implode(', ', array_slice($brandTypes, 0, 5)) . "...\n";

            // Check if 'coke' is in the types
            if (in_array('coke', $brandTypes)) {
                echo "  ✓ 'coke' is in Brand::types()\n";
            } else {
                echo "  ✗ 'coke' is NOT in Brand::types()!\n";
            }
        } else {
            echo "Brands relationship is NULL!\n";
        }

        // Now check what Photo::tags() returns
        echo "\n=== CHECKING PHOTO::TAGS() OUTPUT ===\n";
        $tags = $photo->tags();
        echo "Tags returned by photo->tags():\n";
        echo json_encode($tags, JSON_PRETTY_PRINT) . "\n";

        // Check if brands are in the tags
        if (isset($tags['brands'])) {
            echo "\n✓ 'brands' key exists in tags\n";
            echo "Brands content: " . json_encode($tags['brands']) . "\n";
        } else {
            echo "\n✗ 'brands' key NOT FOUND in tags!\n";
            echo "Available keys: " . implode(', ', array_keys($tags)) . "\n";
        }

        // Now test the service's getTags method
        echo "\n=== TESTING SERVICE->GETTAGS() ===\n";
        $service = app(UpdateTagsService::class);
        [$originalTags, $customTagsOld] = $service->getTags($photo);

        echo "Original tags from service:\n";
        echo json_encode($originalTags, JSON_PRETTY_PRINT) . "\n";

        // Test parseTags
        echo "\n=== TESTING PARSETAGS ===\n";
        $parsedTags = $service->parseTags($originalTags, $customTagsOld, $photo->id);

        echo "Parsed tags structure:\n";
        echo "  Groups count: " . count($parsedTags['groups']) . "\n";
        echo "  Global brands count: " . count($parsedTags['globalBrands']) . "\n";

        if (!empty($parsedTags['globalBrands'])) {
            echo "  Global brands:\n";
            foreach ($parsedTags['globalBrands'] as $brand) {
                echo "    - {$brand['key']} (ID: {$brand['id']}, Qty: {$brand['quantity']})\n";
            }
        } else {
            echo "  ✗ NO GLOBAL BRANDS PARSED!\n";
        }

        // This should help us see where the brands are getting lost
        $this->assertNotEmpty($tags, "Tags should not be empty");
        $this->assertArrayHasKey('brands', $tags, "Tags should have 'brands' key");
        $this->assertNotEmpty($parsedTags['globalBrands'], "Global brands should be parsed");
    }
}
