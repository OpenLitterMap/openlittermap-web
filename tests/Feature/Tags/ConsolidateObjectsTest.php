<?php

namespace Tests\Feature\Tags;

use App\Enums\CategoryKey;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CategoryObject;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\LitterObjectType;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Litter\Tags\Taggable;
use App\Models\Photo;
use App\Tags\TagsConfig;
use Database\Seeders\Tags\GenerateTagsSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConsolidateObjectsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GenerateTagsSeeder::class);
    }

    // ─── Seeder: canonical objects ───

    public function test_seeder_creates_all_canonical_categories(): void
    {
        $config = TagsConfig::get();

        foreach (array_keys($config) as $categoryKey) {
            $this->assertDatabaseHas('categories', ['key' => $categoryKey]);
        }
    }

    public function test_seeder_creates_all_canonical_objects(): void
    {
        $config = TagsConfig::get();
        $allObjects = [];

        foreach ($config as $objects) {
            foreach (array_keys($objects) as $objectKey) {
                $allObjects[$objectKey] = true;
            }
        }

        foreach (array_keys($allObjects) as $objectKey) {
            $this->assertDatabaseHas('litter_objects', ['key' => $objectKey]);
        }
    }

    public function test_seeder_creates_clo_for_every_category_object_pair(): void
    {
        $config = TagsConfig::get();

        foreach ($config as $categoryKey => $objects) {
            $category = Category::where('key', $categoryKey)->first();
            $this->assertNotNull($category, "Category '{$categoryKey}' should exist");

            foreach (array_keys($objects) as $objectKey) {
                $object = LitterObject::where('key', $objectKey)->first();
                $this->assertNotNull($object, "Object '{$objectKey}' should exist");

                $this->assertDatabaseHas('category_litter_object', [
                    'category_id' => $category->id,
                    'litter_object_id' => $object->id,
                ]);
            }
        }
    }

    public function test_seeder_creates_type_associations_for_typed_objects(): void
    {
        $config = TagsConfig::get();

        foreach ($config as $categoryKey => $objects) {
            $category = Category::where('key', $categoryKey)->first();

            foreach ($objects as $objectKey => $attributes) {
                if (empty($attributes['types'])) {
                    continue;
                }

                $object = LitterObject::where('key', $objectKey)->first();
                $clo = CategoryObject::where('category_id', $category->id)
                    ->where('litter_object_id', $object->id)
                    ->first();

                $typeKeys = $clo->types->pluck('key')->sort()->values()->toArray();
                $expectedKeys = collect($attributes['types'])->sort()->values()->toArray();

                $this->assertEquals(
                    $expectedKeys,
                    $typeKeys,
                    "Types for {$categoryKey}/{$objectKey} should match config"
                );
            }
        }
    }

    public function test_seeder_creates_material_associations(): void
    {
        $config = TagsConfig::get();
        $category = Category::where('key', CategoryKey::Smoking->value)->first();
        $object = LitterObject::where('key', 'butts')->first();

        $clo = CategoryObject::where('category_id', $category->id)
            ->where('litter_object_id', $object->id)
            ->first();

        $materialKeys = $clo->materials->pluck('key')->sort()->values()->toArray();
        $expected = collect($config['smoking']['butts']['materials'])->sort()->values()->toArray();

        $this->assertEquals($expected, $materialKeys);
    }

    public function test_seeder_is_idempotent(): void
    {
        $catCount = Category::count();
        $objCount = LitterObject::count();
        $cloCount = CategoryObject::count();
        $cotCount = DB::table('category_object_types')->count();

        // Run seeder again
        $this->seed(GenerateTagsSeeder::class);

        $this->assertEquals($catCount, Category::count());
        $this->assertEquals($objCount, LitterObject::count());
        $this->assertEquals($cloCount, CategoryObject::count());
        $this->assertEquals($cotCount, DB::table('category_object_types')->count());
    }

    // ─── Mapping: prefixed → canonical ───

    public function test_beer_bottle_maps_to_alcohol_bottle_with_beer_type(): void
    {
        $oldObject = LitterObject::firstOrCreate(['key' => 'beer_bottle']);
        $oldCategory = Category::where('key', CategoryKey::Alcohol->value)->first();

        $photo = Photo::factory()->create();
        PhotoTag::create([
            'photo_id' => $photo->id,
            'category_id' => $oldCategory->id,
            'litter_object_id' => $oldObject->id,
            'category_litter_object_id' => $this->getCloId($oldCategory->id, $oldObject->id),
            'quantity' => 3,
        ]);

        $this->artisan('olm:consolidate-objects')
            ->assertExitCode(0);

        $tag = PhotoTag::where('photo_id', $photo->id)->first();

        $this->assertEquals('alcohol', Category::find($tag->category_id)->key);
        $this->assertEquals('bottle', LitterObject::find($tag->litter_object_id)->key);
        $this->assertEquals('beer', LitterObjectType::find($tag->litter_object_type_id)->key);
        $this->assertNotNull($tag->category_litter_object_id);
    }

    public function test_water_bottle_maps_to_softdrinks_bottle_with_water_type(): void
    {
        $oldObject = LitterObject::firstOrCreate(['key' => 'water_bottle']);
        $softdrinksCat = Category::firstOrCreate(['key' => CategoryKey::Softdrinks->value]);

        $photo = Photo::factory()->create();
        PhotoTag::create([
            'photo_id' => $photo->id,
            'category_id' => $softdrinksCat->id,
            'litter_object_id' => $oldObject->id,
            'category_litter_object_id' => $this->getCloId($softdrinksCat->id, $oldObject->id),
            'quantity' => 1,
        ]);

        $this->artisan('olm:consolidate-objects')
            ->assertExitCode(0);

        $tag = PhotoTag::where('photo_id', $photo->id)->first();

        $this->assertEquals('softdrinks', Category::find($tag->category_id)->key);
        $this->assertEquals('bottle', LitterObject::find($tag->litter_object_id)->key);
        $this->assertEquals('water', LitterObjectType::find($tag->litter_object_type_id)->key);
    }

    public function test_camel_case_object_maps_to_snake_case(): void
    {
        $oldObject = LitterObject::firstOrCreate(['key' => 'tobaccoPouch']);
        $smokingCat = Category::where('key', CategoryKey::Smoking->value)->first();

        $photo = Photo::factory()->create();
        PhotoTag::create([
            'photo_id' => $photo->id,
            'category_id' => $smokingCat->id,
            'litter_object_id' => $oldObject->id,
            'category_litter_object_id' => $this->getCloId($smokingCat->id, $oldObject->id),
            'quantity' => 1,
        ]);

        $this->artisan('olm:consolidate-objects')
            ->assertExitCode(0);

        $tag = PhotoTag::where('photo_id', $photo->id)->first();

        $this->assertEquals('tobacco_pouch', LitterObject::find($tag->litter_object_id)->key);
        $this->assertEquals('smoking', Category::find($tag->category_id)->key);
        $this->assertNotNull($tag->category_litter_object_id);
    }

    public function test_category_merge_softdrinks_cup_stays_in_softdrinks(): void
    {
        $cup = LitterObject::firstOrCreate(['key' => 'cup']);
        $softdrinksCat = Category::firstOrCreate(['key' => CategoryKey::Softdrinks->value]);

        // Create old CLO so the cup belongs to softdrinks
        CategoryObject::firstOrCreate([
            'category_id' => $softdrinksCat->id,
            'litter_object_id' => $cup->id,
        ]);

        $photo = Photo::factory()->create();
        PhotoTag::create([
            'photo_id' => $photo->id,
            'category_id' => $softdrinksCat->id,
            'litter_object_id' => $cup->id,
            'category_litter_object_id' => $this->getCloId($softdrinksCat->id, $cup->id),
            'quantity' => 2,
        ]);

        $this->artisan('olm:consolidate-objects')
            ->assertExitCode(0);

        $tag = PhotoTag::where('photo_id', $photo->id)->first();

        $this->assertEquals('softdrinks', Category::find($tag->category_id)->key);
        $this->assertEquals('cup', LitterObject::find($tag->litter_object_id)->key);
        $this->assertNotNull($tag->category_litter_object_id);
    }

    public function test_sanitary_syringe_maps_to_medical(): void
    {
        $syringe = LitterObject::firstOrCreate(['key' => 'syringe']);
        $sanitaryCat = Category::firstOrCreate(['key' => CategoryKey::Sanitary->value]);

        $photo = Photo::factory()->create();
        PhotoTag::create([
            'photo_id' => $photo->id,
            'category_id' => $sanitaryCat->id,
            'litter_object_id' => $syringe->id,
            'category_litter_object_id' => $this->getCloId($sanitaryCat->id, $syringe->id),
            'quantity' => 1,
        ]);

        $this->artisan('olm:consolidate-objects')
            ->assertExitCode(0);

        $tag = PhotoTag::where('photo_id', $photo->id)->first();

        $this->assertEquals('medical', Category::find($tag->category_id)->key);
        $this->assertEquals('syringe', LitterObject::find($tag->litter_object_id)->key);
    }

    public function test_automobile_maps_to_vehicles(): void
    {
        $carPart = LitterObject::firstOrCreate(['key' => 'car_part']);
        $automobileCat = Category::firstOrCreate(['key' => CategoryKey::Automobile->value]);

        CategoryObject::firstOrCreate([
            'category_id' => $automobileCat->id,
            'litter_object_id' => $carPart->id,
        ]);

        $photo = Photo::factory()->create();
        PhotoTag::create([
            'photo_id' => $photo->id,
            'category_id' => $automobileCat->id,
            'litter_object_id' => $carPart->id,
            'category_litter_object_id' => $this->getCloId($automobileCat->id, $carPart->id),
            'quantity' => 1,
        ]);

        $this->artisan('olm:consolidate-objects')
            ->assertExitCode(0);

        $tag = PhotoTag::where('photo_id', $photo->id)->first();

        $this->assertEquals('vehicles', Category::find($tag->category_id)->key);
    }

    // ─── Taggables remap ───

    public function test_taggable_remaps_to_new_clo(): void
    {
        $coffeeCat = Category::firstOrCreate(['key' => CategoryKey::Coffee->value]);
        $cup = LitterObject::firstOrCreate(['key' => 'cup']);

        $oldCLO = CategoryObject::firstOrCreate([
            'category_id' => $coffeeCat->id,
            'litter_object_id' => $cup->id,
        ]);

        // Create a taggable linked to the old CLO
        $material = \App\Models\Litter\Tags\Materials::firstOrCreate(['key' => 'plastic']);
        Taggable::create([
            'category_litter_object_id' => $oldCLO->id,
            'taggable_type' => 'App\\Models\\Litter\\Tags\\Materials',
            'taggable_id' => $material->id,
        ]);

        $this->artisan('olm:consolidate-objects')
            ->assertExitCode(0);

        $softdrinksCat = Category::where('key', CategoryKey::Softdrinks->value)->first();
        $newCLO = CategoryObject::where('category_id', $softdrinksCat->id)
            ->where('litter_object_id', $cup->id)
            ->first();

        // Taggable should now point to softdrinks/cup CLO
        $taggable = Taggable::where('taggable_type', 'App\\Models\\Litter\\Tags\\Materials')
            ->where('taggable_id', $material->id)
            ->where('category_litter_object_id', $newCLO->id)
            ->first();

        $this->assertNotNull($taggable);
    }

    public function test_taggable_remap_handles_duplicates(): void
    {
        $coffeeCat = Category::firstOrCreate(['key' => CategoryKey::Coffee->value]);
        $cup = LitterObject::firstOrCreate(['key' => 'cup']);

        $oldCLO = CategoryObject::firstOrCreate([
            'category_id' => $coffeeCat->id,
            'litter_object_id' => $cup->id,
        ]);

        $softdrinksCat = Category::where('key', CategoryKey::Softdrinks->value)->first();
        $newCLO = CategoryObject::where('category_id', $softdrinksCat->id)
            ->where('litter_object_id', $cup->id)
            ->first();

        $material = \App\Models\Litter\Tags\Materials::firstOrCreate(['key' => 'plastic']);

        // Taggable on old CLO (coffee/cup)
        Taggable::create([
            'category_litter_object_id' => $oldCLO->id,
            'taggable_type' => 'App\\Models\\Litter\\Tags\\Materials',
            'taggable_id' => $material->id,
        ]);

        // Same taggable already on new CLO (softdrinks/cup — duplicate scenario)
        Taggable::create([
            'category_litter_object_id' => $newCLO->id,
            'taggable_type' => 'App\\Models\\Litter\\Tags\\Materials',
            'taggable_id' => $material->id,
        ]);

        $this->artisan('olm:consolidate-objects')
            ->assertExitCode(0);

        // Should only have one taggable on the new CLO, not two
        $count = Taggable::where('category_litter_object_id', $newCLO->id)
            ->where('taggable_type', 'App\\Models\\Litter\\Tags\\Materials')
            ->where('taggable_id', $material->id)
            ->count();

        $this->assertEquals(1, $count);

        // Old CLO should have no taggables for this material
        $oldCount = Taggable::where('category_litter_object_id', $oldCLO->id)
            ->where('taggable_type', 'App\\Models\\Litter\\Tags\\Materials')
            ->where('taggable_id', $material->id)
            ->count();

        $this->assertEquals(0, $oldCount);
    }

    // ─── Null-null fallback ───

    public function test_null_null_tag_with_clo_is_unchanged_by_consolidation(): void
    {
        $unclassifiedCat = Category::firstOrCreate(['key' => CategoryKey::Unclassified->value]);
        $otherObj = LitterObject::firstOrCreate(['key' => 'other']);
        $cloId = $this->getCloId($unclassifiedCat->id, $otherObj->id);

        $photo = Photo::factory()->create();
        PhotoTag::create([
            'photo_id' => $photo->id,
            'category_id' => null,
            'litter_object_id' => null,
            'category_litter_object_id' => $cloId,
            'quantity' => 1,
        ]);

        $this->artisan('olm:consolidate-objects')
            ->assertExitCode(0);

        $tag = PhotoTag::where('photo_id', $photo->id)->first();

        // CLO is retained (command's null-null handler targets whereNull CLO)
        $this->assertEquals($cloId, $tag->category_litter_object_id);
        // Denorm fields stay null — integrity command handles repair
        $this->assertNull($tag->category_id);
        $this->assertNull($tag->litter_object_id);
    }

    public function test_partial_null_tag_with_clo_is_unchanged_by_consolidation(): void
    {
        $category = Category::where('key', CategoryKey::Smoking->value)->first();
        $butts = LitterObject::where('key', 'butts')->first();
        $cloId = $this->getCloId($category->id, $butts->id);

        $photo = Photo::factory()->create();
        PhotoTag::create([
            'photo_id' => $photo->id,
            'category_id' => $category->id,
            'litter_object_id' => null,
            'category_litter_object_id' => $cloId,
            'quantity' => 1,
        ]);

        $this->artisan('olm:consolidate-objects')
            ->assertExitCode(0);

        $tag = PhotoTag::where('photo_id', $photo->id)->first();

        // Tag unchanged — CLO retained, denorm fields as-is
        $this->assertEquals($cloId, $tag->category_litter_object_id);
        $this->assertEquals($category->id, $tag->category_id);
        $this->assertNull($tag->litter_object_id);
    }

    // ─── Backfill ───

    public function test_existing_canonical_tag_gets_clo_backfilled(): void
    {
        $category = Category::where('key', CategoryKey::Smoking->value)->first();
        $object = LitterObject::where('key', 'butts')->first();

        $photo = Photo::factory()->create();
        PhotoTag::create([
            'photo_id' => $photo->id,
            'category_id' => $category->id,
            'litter_object_id' => $object->id,
            'category_litter_object_id' => $this->getCloId($category->id, $object->id),
            'quantity' => 5,
        ]);

        $this->artisan('olm:consolidate-objects')
            ->assertExitCode(0);

        $tag = PhotoTag::where('photo_id', $photo->id)->first();

        $this->assertNotNull($tag->category_litter_object_id);
        $clo = CategoryObject::find($tag->category_litter_object_id);
        $this->assertEquals($category->id, $clo->category_id);
        $this->assertEquals($object->id, $clo->litter_object_id);
    }

    public function test_tag_with_existing_clo_is_not_changed(): void
    {
        $category = Category::where('key', CategoryKey::Smoking->value)->first();
        $object = LitterObject::where('key', 'butts')->first();
        $clo = CategoryObject::where('category_id', $category->id)
            ->where('litter_object_id', $object->id)
            ->first();

        $photo = Photo::factory()->create();
        PhotoTag::create([
            'photo_id' => $photo->id,
            'category_id' => $category->id,
            'litter_object_id' => $object->id,
            'category_litter_object_id' => $clo->id,
            'quantity' => 2,
        ]);

        $this->artisan('olm:consolidate-objects')
            ->assertExitCode(0);

        $tag = PhotoTag::where('photo_id', $photo->id)->first();
        $this->assertEquals($clo->id, $tag->category_litter_object_id);
    }

    // ─── Idempotency ───

    public function test_running_twice_produces_same_result(): void
    {
        $oldObject = LitterObject::firstOrCreate(['key' => 'beer_bottle']);
        $alcoholCat = Category::where('key', CategoryKey::Alcohol->value)->first();

        $photo = Photo::factory()->create();
        PhotoTag::create([
            'photo_id' => $photo->id,
            'category_id' => $alcoholCat->id,
            'litter_object_id' => $oldObject->id,
            'category_litter_object_id' => $this->getCloId($alcoholCat->id, $oldObject->id),
            'quantity' => 1,
        ]);

        // Run once
        $this->artisan('olm:consolidate-objects')
            ->assertExitCode(0);

        $tagAfterFirst = PhotoTag::where('photo_id', $photo->id)->first();
        $firstCategoryId = $tagAfterFirst->category_id;
        $firstObjectId = $tagAfterFirst->litter_object_id;
        $firstCloId = $tagAfterFirst->category_litter_object_id;
        $firstTypeId = $tagAfterFirst->litter_object_type_id;

        // Run again
        $this->artisan('olm:consolidate-objects')
            ->assertExitCode(0);

        $tagAfterSecond = PhotoTag::where('photo_id', $photo->id)->first();
        $this->assertEquals($firstCategoryId, $tagAfterSecond->category_id);
        $this->assertEquals($firstObjectId, $tagAfterSecond->litter_object_id);
        $this->assertEquals($firstCloId, $tagAfterSecond->category_litter_object_id);
        $this->assertEquals($firstTypeId, $tagAfterSecond->litter_object_type_id);
    }

    // ─── Dry-run ───

    public function test_dry_run_makes_no_database_changes(): void
    {
        $oldObject = LitterObject::firstOrCreate(['key' => 'beer_bottle']);
        $alcoholCat = Category::where('key', CategoryKey::Alcohol->value)->first();

        $photo = Photo::factory()->create();
        $tag = PhotoTag::create([
            'photo_id' => $photo->id,
            'category_id' => $alcoholCat->id,
            'litter_object_id' => $oldObject->id,
            'category_litter_object_id' => $this->getCloId($alcoholCat->id, $oldObject->id),
            'quantity' => 1,
        ]);

        $originalCategoryId = $tag->category_id;
        $originalObjectId = $tag->litter_object_id;
        $originalCloId = $tag->category_litter_object_id;

        $this->artisan('olm:consolidate-objects', ['--dry-run' => true])
            ->assertExitCode(0);

        $tag->refresh();
        $this->assertEquals($originalCategoryId, $tag->category_id);
        $this->assertEquals($originalObjectId, $tag->litter_object_id);
        $this->assertEquals($originalCloId, $tag->category_litter_object_id);
        $this->assertNull($tag->litter_object_type_id);
    }

    public function test_dry_run_does_not_remap_taggables(): void
    {
        $softdrinksCat = Category::firstOrCreate(['key' => CategoryKey::Softdrinks->value]);
        $cup = LitterObject::firstOrCreate(['key' => 'cup']);

        $oldCLO = CategoryObject::firstOrCreate([
            'category_id' => $softdrinksCat->id,
            'litter_object_id' => $cup->id,
        ]);

        $material = \App\Models\Litter\Tags\Materials::firstOrCreate(['key' => 'plastic']);
        $taggable = Taggable::create([
            'category_litter_object_id' => $oldCLO->id,
            'taggable_type' => 'App\\Models\\Litter\\Tags\\Materials',
            'taggable_id' => $material->id,
        ]);

        $this->artisan('olm:consolidate-objects', ['--dry-run' => true])
            ->assertExitCode(0);

        $taggable->refresh();
        $this->assertEquals($oldCLO->id, $taggable->category_litter_object_id);
    }

    public function test_dry_run_does_not_fix_null_null_tags(): void
    {
        $unclassifiedCat = Category::firstOrCreate(['key' => CategoryKey::Unclassified->value]);
        $otherObj = LitterObject::firstOrCreate(['key' => 'other']);
        $cloId = $this->getCloId($unclassifiedCat->id, $otherObj->id);

        $photo = Photo::factory()->create();
        $tag = PhotoTag::create([
            'photo_id' => $photo->id,
            'category_id' => null,
            'litter_object_id' => null,
            'category_litter_object_id' => $cloId,
            'quantity' => 1,
        ]);

        $this->artisan('olm:consolidate-objects', ['--dry-run' => true])
            ->assertExitCode(0);

        $tag->refresh();
        $this->assertNull($tag->category_id);
        $this->assertNull($tag->litter_object_id);
        $this->assertEquals($cloId, $tag->category_litter_object_id);
    }

    // ─── Quantity preserved ───

    public function test_quantity_is_preserved_during_mapping(): void
    {
        $oldObject = LitterObject::firstOrCreate(['key' => 'beer_bottle']);
        $alcoholCat = Category::where('key', CategoryKey::Alcohol->value)->first();

        $photo = Photo::factory()->create();
        PhotoTag::create([
            'photo_id' => $photo->id,
            'category_id' => $alcoholCat->id,
            'litter_object_id' => $oldObject->id,
            'category_litter_object_id' => $this->getCloId($alcoholCat->id, $oldObject->id),
            'quantity' => 42,
        ]);

        $this->artisan('olm:consolidate-objects')
            ->assertExitCode(0);

        $tag = PhotoTag::where('photo_id', $photo->id)->first();
        $this->assertEquals(42, $tag->quantity);
    }

    // ─── Multiple tags on one photo ───

    public function test_multiple_tags_on_same_photo_all_mapped(): void
    {
        $photo = Photo::factory()->create();

        $beerBottle = LitterObject::firstOrCreate(['key' => 'beer_bottle']);
        $waterBottle = LitterObject::firstOrCreate(['key' => 'water_bottle']);
        $alcoholCat = Category::where('key', CategoryKey::Alcohol->value)->first();
        $softdrinksCat = Category::firstOrCreate(['key' => CategoryKey::Softdrinks->value]);

        PhotoTag::create([
            'photo_id' => $photo->id,
            'category_id' => $alcoholCat->id,
            'litter_object_id' => $beerBottle->id,
            'category_litter_object_id' => $this->getCloId($alcoholCat->id, $beerBottle->id),
            'quantity' => 2,
        ]);

        PhotoTag::create([
            'photo_id' => $photo->id,
            'category_id' => $softdrinksCat->id,
            'litter_object_id' => $waterBottle->id,
            'category_litter_object_id' => $this->getCloId($softdrinksCat->id, $waterBottle->id),
            'quantity' => 3,
        ]);

        $this->artisan('olm:consolidate-objects')
            ->assertExitCode(0);

        $tags = PhotoTag::where('photo_id', $photo->id)->get();
        $this->assertCount(2, $tags);

        // Both should now point to canonical 'bottle' object
        foreach ($tags as $tag) {
            $this->assertEquals('bottle', LitterObject::find($tag->litter_object_id)->key);
        }

        // Find beer tag by type
        $beerTag = $tags->first(function ($t) {
            $type = LitterObjectType::find($t->litter_object_type_id);
            return $type && $type->key === 'beer';
        });
        $this->assertNotNull($beerTag);
        $this->assertEquals('alcohol', Category::find($beerTag->category_id)->key);
        $this->assertEquals(2, $beerTag->quantity);

        // Find water tag by type
        $waterTag = $tags->first(function ($t) {
            $type = LitterObjectType::find($t->litter_object_type_id);
            return $type && $type->key === 'water';
        });
        $this->assertNotNull($waterTag);
        $this->assertEquals('softdrinks', Category::find($waterTag->category_id)->key);
        $this->assertEquals(3, $waterTag->quantity);
    }
}
