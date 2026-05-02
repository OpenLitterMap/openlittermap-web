<?php

namespace Tests\Unit\Exports;

use App\Exports\CreateCSVExport;
use App\Mail\ExportFailed;
use Illuminate\Support\Facades\Mail;
use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CategoryObject;
use App\Models\Litter\Tags\CustomTagNew;
use App\Models\Litter\Tags\LitterObjectType;
use App\Models\Litter\Tags\Materials;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Litter\Tags\PhotoTagExtraTags;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Photo;
use App\Models\Users\User;
use App\Services\Tags\GeneratePhotoSummaryService;
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

    public function test_mapped_cells_are_all_stringifiable_for_phpspreadsheet()
    {
        // Regression: PhpSpreadsheet's DefaultValueBinder casts every cell to string.
        // A raw backed enum (e.g. VerificationStatus) has no __toString() and fatals the export.
        // Guard: every cell returned by map() must be scalar, null, or Stringable.
        // Covers BOTH user-export and team-export branches (team branch was the one that
        // failed in production — Horizon job 5c86f106, team_id 211).
        $team = \App\Models\Teams\Team::factory()->create();
        $user = User::factory()->create();
        $photo = Photo::factory()->create([
            'verified' => 2,
            'user_id' => $user->id,
            'team_id' => $team->id,
            'datetime' => now()->toDateTimeString(),
            'lat' => 42.0,
            'lon' => 42.0,
            'address_array' => ['country' => 'Ireland'],
            'summary' => ['tags' => [], 'totals' => ['litter' => 0, 'materials' => 0, 'brands' => 0, 'custom_tags' => 0]],
        ]);

        $userExport = new CreateCSVExport(null, null, null, $user->id);
        $teamExport = new CreateCSVExport(null, null, $team->id, null);

        foreach ([$userExport, $teamExport] as $export) {
            $mapped = $export->map($photo->fresh());

            foreach ($mapped as $i => $cell) {
                $this->assertTrue(
                    $cell === null || is_scalar($cell) || $cell instanceof \Stringable,
                    "Cell {$i} is not stringifiable: " . (is_object($cell) ? get_class($cell) : gettype($cell))
                );
            }

            // verified column must be the int value, not the enum instance
            $this->assertSame(2, $mapped[1]);
        }
    }

    public function test_failed_method_exists_with_throwable_signature()
    {
        // Maatwebsite's ProxyFailures trait calls $this->sheetExport->failed($e) via direct
        // method invocation. If someone renames or removes the method, the regression test
        // for the email behaviour would still pass (Mail::fake intercepts before the dispatch
        // pipeline) but the production failure-notification path would silently break.
        // Pin the method existence and signature.
        $this->assertTrue(method_exists(CreateCSVExport::class, 'failed'));

        $reflection = new \ReflectionMethod(CreateCSVExport::class, 'failed');
        $params = $reflection->getParameters();
        $this->assertCount(1, $params);
        $this->assertSame('Throwable', (string) $params[0]->getType());
    }

    public function test_failed_hook_emails_the_user()
    {
        Mail::fake();

        $export = (new CreateCSVExport(null, null, null, 1))
            ->notifyOnFailure('user@example.com');

        $export->failed(new \RuntimeException('boom'));

        Mail::assertSent(ExportFailed::class, fn ($mail) => $mail->hasTo('user@example.com'));
    }

    public function test_failed_hook_is_noop_without_notify_email()
    {
        Mail::fake();

        $export = new CreateCSVExport(null, null, null, 1);
        $export->failed(new \RuntimeException('boom'));

        Mail::assertNothingSent();
    }

    public function test_migrated_alcohol_bottle_with_subtype_and_inferred_material_populates_columns()
    {
        // Regression: pre-Apr-12 CSV export iterated summary as a nested {catId:{objId:...}}
        // structure, but the v5.1 summary is a flat array. Migrated alcohol/softdrinks photos
        // (where the v4 spiritBottle/beerBottle/wineBottle/beerCan/water/fizzy keys collapse to
        // (litter_object=bottle|can, litter_object_type=spirits|beer|wine|water|...)) had blank
        // ALCOHOL.bottle / TYPES.spirits / MATERIALS.glass columns in the CSV. This test pins
        // the post-fix behaviour against a real GeneratePhotoSummaryService run so any future
        // summary-shape change re-trips the regression.
        $alcohol = Category::firstOrCreate(['key' => 'alcohol']);
        $bottle = LitterObject::firstOrCreate(['key' => 'bottle']);
        $can = LitterObject::firstOrCreate(['key' => 'can']);
        $spirits = LitterObjectType::firstOrCreate(['key' => 'spirits']);
        $beer = LitterObjectType::firstOrCreate(['key' => 'beer']);
        $glass = Materials::firstOrCreate(['key' => 'glass']);
        $aluminium = Materials::firstOrCreate(['key' => 'aluminium']);

        $bottleCloId = CategoryObject::firstOrCreate([
            'category_id' => $alcohol->id,
            'litter_object_id' => $bottle->id,
        ])->id;
        $canCloId = CategoryObject::firstOrCreate([
            'category_id' => $alcohol->id,
            'litter_object_id' => $can->id,
        ])->id;

        $user = User::factory()->create();
        $photo = Photo::factory()->create([
            'verified' => 2,
            'user_id' => $user->id,
            'datetime' => now()->toDateTimeString(),
            'lat' => 42.0,
            'lon' => 42.0,
            'address_array' => ['country' => 'Ireland'],
            'remaining' => false,
            'migrated_at' => now(),
        ]);

        // Spirit bottle: object=bottle, type=spirits, material=glass — qty 1
        $ptSpirit = PhotoTag::create([
            'photo_id' => $photo->id,
            'category_id' => $alcohol->id,
            'litter_object_id' => $bottle->id,
            'category_litter_object_id' => $bottleCloId,
            'litter_object_type_id' => $spirits->id,
            'quantity' => 1,
            'picked_up' => true,
        ]);
        PhotoTagExtraTags::create(['photo_tag_id' => $ptSpirit->id, 'tag_type' => 'material', 'tag_type_id' => $glass->id, 'quantity' => 1]);

        // Beer can: object=can, type=beer, material=aluminium — qty 3
        $ptBeerCan = PhotoTag::create([
            'photo_id' => $photo->id,
            'category_id' => $alcohol->id,
            'litter_object_id' => $can->id,
            'category_litter_object_id' => $canCloId,
            'litter_object_type_id' => $beer->id,
            'quantity' => 3,
            'picked_up' => true,
        ]);
        PhotoTagExtraTags::create(['photo_tag_id' => $ptBeerCan->id, 'tag_type' => 'material', 'tag_type_id' => $aluminium->id, 'quantity' => 1]);

        // Beer bottle: object=bottle (collapses with spirit), type=beer, material=glass — qty 2
        $ptBeerBottle = PhotoTag::create([
            'photo_id' => $photo->id,
            'category_id' => $alcohol->id,
            'litter_object_id' => $bottle->id,
            'category_litter_object_id' => $bottleCloId,
            'litter_object_type_id' => $beer->id,
            'quantity' => 2,
            'picked_up' => true,
        ]);
        PhotoTagExtraTags::create(['photo_tag_id' => $ptBeerBottle->id, 'tag_type' => 'material', 'tag_type_id' => $glass->id, 'quantity' => 1]);

        // Build summary the real way — through the service. Tests the actual pipeline rather
        // than a hand-built JSON which could mask a regression in summary structure.
        app(GeneratePhotoSummaryService::class)->run($photo->fresh());

        $export = new CreateCSVExport(null, null, null, $user->id);
        $headings = $export->headings();
        $mapped = $export->map($photo->fresh());

        $alcoholHeader = strtoupper($alcohol->key);
        $alcoholHeaderIndex = array_search($alcoholHeader, $headings);
        $this->assertNotFalse($alcoholHeaderIndex, 'ALCOHOL category section is missing');

        // ALCOHOL.bottle = 1 (spirit) + 2 (beer) = 3
        $bottleIndex = array_search('bottle', $headings);
        $this->assertNotFalse($bottleIndex);
        $this->assertEquals(3, $mapped[$bottleIndex], 'ALCOHOL.bottle column should aggregate spirit + beer bottle quantities');

        // ALCOHOL.can = 3 (beer can)
        $canIndex = array_search('can', $headings);
        $this->assertNotFalse($canIndex);
        $this->assertEquals(3, $mapped[$canIndex], 'ALCOHOL.can column should equal beer can quantity');

        // TYPES.spirits = 1, TYPES.beer = 5 (2 bottle + 3 can)
        $typesIndex = array_search('TYPES', $headings);
        $this->assertNotFalse($typesIndex);
        $spiritsIndex = array_search('spirits', $headings);
        $beerIndex = array_search('beer', $headings);
        $this->assertNotFalse($spiritsIndex);
        $this->assertNotFalse($beerIndex);
        $this->assertEquals(1, $mapped[$spiritsIndex], 'TYPES.spirits column should equal spirit bottle quantity');
        $this->assertEquals(5, $mapped[$beerIndex], 'TYPES.beer column should aggregate beer bottle + beer can quantities');

        // MATERIALS.glass = 1 (spirit) + 2 (beer bottle) = 3 (parent qty)
        // MATERIALS.aluminium = 3 (beer can parent qty)
        $materialsIndex = array_search('MATERIALS', $headings);
        $this->assertNotFalse($materialsIndex);
        $glassIndex = array_search('glass', $headings);
        $aluminiumIndex = array_search('aluminium', $headings);
        $this->assertNotFalse($glassIndex);
        $this->assertNotFalse($aluminiumIndex);
        $this->assertEquals(3, $mapped[$glassIndex], 'MATERIALS.glass column should aggregate parent qty across bottle PhotoTags');
        $this->assertEquals(3, $mapped[$aluminiumIndex], 'MATERIALS.aluminium column should equal beer can parent qty');
    }

    public function test_total_tags_column_includes_custom_only_brand_only_and_material_extras()
    {
        // Regression: CSV `total_tags` column previously read summary.totals.litter, which is
        // objects-only. Custom-only and brand-only photos showed 0 even though they have tags.
        // The fix reads $row->total_tags (DB column), set by GeneratePhotoSummaryService to
        // include objects + materials + brands + custom tags.
        $category = Category::with(['litterObjects' => fn ($q) => $q->orderBy('litter_objects.id')])
            ->orderBy('id')
            ->get()
            ->first(fn ($c) => $c->litterObjects->count() >= 1);
        $obj = $category->litterObjects->first();
        $cloId = CategoryObject::where('category_id', $category->id)->where('litter_object_id', $obj->id)->value('id');
        $unclassifiedCloId = $this->getUnclassifiedOtherCloId();

        $material = Materials::orderBy('id')->first();
        $brand = BrandList::firstOrCreate(['key' => 'totals_brand']);
        $customTag = CustomTagNew::firstOrCreate(['key' => 'totals_custom']);

        $user = User::factory()->create();

        // Custom-only photo: one PhotoTag with no object, just a custom tag extra
        $customOnlyPhoto = Photo::factory()->create([
            'verified' => 2,
            'user_id' => $user->id,
            'datetime' => now()->toDateTimeString(),
            'lat' => 42.0,
            'lon' => 42.0,
            'address_array' => ['country' => 'Ireland'],
            'total_tags' => 1,
            'summary' => [
                'tags' => [
                    ['clo_id' => $unclassifiedCloId, 'category_id' => 0, 'object_id' => 0, 'type_id' => null, 'quantity' => 1, 'materials' => [], 'brands' => (object) [], 'custom_tags' => [$customTag->id]],
                ],
                'totals' => ['litter' => 0, 'materials' => 0, 'brands' => 0, 'custom_tags' => 1],
            ],
        ]);
        $ptCustom = PhotoTag::create(['photo_id' => $customOnlyPhoto->id, 'category_litter_object_id' => $unclassifiedCloId, 'quantity' => 1]);
        PhotoTagExtraTags::create(['photo_tag_id' => $ptCustom->id, 'tag_type' => 'custom_tag', 'tag_type_id' => $customTag->id, 'quantity' => 1]);

        // Brand-only photo
        $brandOnlyPhoto = Photo::factory()->create([
            'verified' => 2,
            'user_id' => $user->id,
            'datetime' => now()->toDateTimeString(),
            'lat' => 42.0,
            'lon' => 42.0,
            'address_array' => ['country' => 'Ireland'],
            'total_tags' => 4,
            'summary' => [
                'tags' => [
                    ['clo_id' => $unclassifiedCloId, 'category_id' => 0, 'object_id' => 0, 'type_id' => null, 'quantity' => 4, 'materials' => [], 'brands' => [(string) $brand->id => 4], 'custom_tags' => []],
                ],
                'totals' => ['litter' => 0, 'materials' => 0, 'brands' => 4, 'custom_tags' => 0],
                'keys' => ['brands' => [(string) $brand->id => 'totals_brand']],
            ],
        ]);
        $ptBrand = PhotoTag::create(['photo_id' => $brandOnlyPhoto->id, 'category_litter_object_id' => $unclassifiedCloId, 'quantity' => 4]);
        PhotoTagExtraTags::create(['photo_tag_id' => $ptBrand->id, 'tag_type' => 'brand', 'tag_type_id' => $brand->id, 'quantity' => 4]);

        // Mixed photo: 1 object × qty 3, plus 1 material, 1 brand, 1 custom — grand total 3+3+1+3=10
        $mixedPhoto = Photo::factory()->create([
            'verified' => 2,
            'user_id' => $user->id,
            'datetime' => now()->toDateTimeString(),
            'lat' => 42.0,
            'lon' => 42.0,
            'address_array' => ['country' => 'Ireland'],
            'total_tags' => 10,
            'summary' => [
                'tags' => [
                    ['clo_id' => $cloId, 'category_id' => $category->id, 'object_id' => $obj->id, 'type_id' => null, 'quantity' => 3, 'materials' => [$material->id], 'brands' => [(string) $brand->id => 1], 'custom_tags' => [$customTag->id]],
                ],
                'totals' => ['litter' => 3, 'materials' => 3, 'brands' => 1, 'custom_tags' => 3],
                'keys' => ['brands' => [(string) $brand->id => 'totals_brand']],
            ],
        ]);
        $ptMixed = PhotoTag::create(['photo_id' => $mixedPhoto->id, 'category_id' => $category->id, 'litter_object_id' => $obj->id, 'category_litter_object_id' => $cloId, 'quantity' => 3]);
        PhotoTagExtraTags::create(['photo_tag_id' => $ptMixed->id, 'tag_type' => 'material', 'tag_type_id' => $material->id, 'quantity' => 1]);
        PhotoTagExtraTags::create(['photo_tag_id' => $ptMixed->id, 'tag_type' => 'brand', 'tag_type_id' => $brand->id, 'quantity' => 1]);
        PhotoTagExtraTags::create(['photo_tag_id' => $ptMixed->id, 'tag_type' => 'custom_tag', 'tag_type_id' => $customTag->id, 'quantity' => 1]);

        $export = new CreateCSVExport(null, null, null, $user->id);

        $this->assertSame(1, $export->map($customOnlyPhoto->fresh())[9], 'Custom-only photo should have total_tags = 1');
        $this->assertSame(4, $export->map($brandOnlyPhoto->fresh())[9], 'Brand-only photo should have total_tags = 4');
        $this->assertSame(10, $export->map($mixedPhoto->fresh())[9], 'Mixed photo should have total_tags = 10 (3 objects + 3 materials + 1 brand + 3 custom)');
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
