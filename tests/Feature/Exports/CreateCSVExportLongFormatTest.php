<?php

namespace Tests\Feature\Exports;

use App\Exports\CreateCSVExport;
use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CategoryObject;
use App\Models\Litter\Tags\CustomTagNew;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\LitterObjectType;
use App\Models\Litter\Tags\Materials;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Litter\Tags\PhotoTagExtraTags;
use App\Models\Photo;
use App\Models\Teams\Team;
use App\Models\Users\User;
use App\Services\Tags\GeneratePhotoSummaryService;
use Tests\TestCase;

/**
 * Long-format export: one row per tag dimension (object + each material + each brand + each custom_tag).
 * Per-extra rows, NOT cartesian — `photo_tag_id` column lets users dedupe before SUM.
 *
 * 14 columns: photo_id, datetime, lat, lng, team, verification, category, object, type,
 * material, brand, custom_tag, quantity, photo_tag_id.
 */
class CreateCSVExportLongFormatTest extends TestCase
{
    private User $user;
    private Category $alcohol;
    private LitterObject $bottle;
    private LitterObjectType $spirits;
    private Materials $glass;
    private int $bottleCloId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alcohol = Category::firstOrCreate(['key' => 'alcohol']);
        $this->bottle = LitterObject::firstOrCreate(['key' => 'bottle']);
        $this->spirits = LitterObjectType::firstOrCreate(['key' => 'spirits'], ['name' => 'Spirits']);
        $this->glass = Materials::firstOrCreate(['key' => 'glass']);

        $this->bottleCloId = CategoryObject::firstOrCreate([
            'category_id' => $this->alcohol->id,
            'litter_object_id' => $this->bottle->id,
        ])->id;

        $this->user = User::factory()->create();
    }

    private function makePhoto(?int $teamId = null): Photo
    {
        return Photo::factory()->create([
            'verified' => 2,
            'user_id' => $this->user->id,
            'team_id' => $teamId,
            'datetime' => now()->toDateTimeString(),
            'lat' => 42.0,
            'lon' => 42.0,
            'address_array' => ['country' => 'Ireland'],
        ]);
    }

    private function exportLong(): CreateCSVExport
    {
        return new CreateCSVExport(null, null, null, $this->user->id, [], [], [], 'long');
    }

    public function test_headings_are_the_14_long_columns_in_order()
    {
        $export = $this->exportLong();
        $this->assertEquals([
            'photo_id', 'datetime', 'lat', 'lng', 'team', 'verification',
            'category', 'object', 'type', 'material', 'brand', 'custom_tag',
            'quantity', 'photo_tag_id',
        ], $export->headings());
    }

    public function test_username_column_does_not_exist()
    {
        // Privacy v1 default: no username column at all.
        $this->assertNotContains('username', $this->exportLong()->headings());
    }

    public function test_single_object_tag_no_extras_emits_one_bare_object_row()
    {
        $photo = $this->makePhoto();
        $pt = PhotoTag::create([
            'photo_id' => $photo->id,
            'category_id' => $this->alcohol->id,
            'litter_object_id' => $this->bottle->id,
            'category_litter_object_id' => $this->bottleCloId,
            'litter_object_type_id' => $this->spirits->id,
            'quantity' => 4,
        ]);
        app(GeneratePhotoSummaryService::class)->run($photo->fresh());

        $rows = $this->exportLong()->map(Photo::with(['photoTags.extraTags.extraTag', 'team'])->find($photo->id));

        $this->assertCount(1, $rows);
        $this->assertEquals('alcohol', $rows[0][6]);
        $this->assertEquals('bottle', $rows[0][7]);
        $this->assertEquals('spirits', $rows[0][8]);
        $this->assertEquals('', $rows[0][9]);  // material
        $this->assertEquals('', $rows[0][10]); // brand
        $this->assertEquals('', $rows[0][11]); // custom_tag
        $this->assertEquals(4, $rows[0][12]);  // quantity = parent qty
        $this->assertEquals($pt->id, $rows[0][13]);
    }

    /**
     * Long format also emits the verification enum (column 6). Drive the FULL
     * PhpSpreadsheet writer (raw()) — not map() — so the value binder is exercised.
     * Pre-fix this 500'd with "could not be converted to string".
     */
    public function test_long_real_writer_pipeline_does_not_choke_on_verification_enum(): void
    {
        $photo = $this->makePhoto();
        PhotoTag::create([
            'photo_id' => $photo->id,
            'category_id' => $this->alcohol->id,
            'litter_object_id' => $this->bottle->id,
            'category_litter_object_id' => $this->bottleCloId,
            'litter_object_type_id' => $this->spirits->id,
            'quantity' => 1,
        ]);
        app(GeneratePhotoSummaryService::class)->run($photo->fresh());

        $csv = $this->exportLong()->raw(\Maatwebsite\Excel\Excel::CSV);

        $this->assertIsString($csv);
        $rows = array_map('str_getcsv', array_values(array_filter(
            explode("\n", str_replace("\r", '', trim($csv)))
        )));
        $verificationIndex = array_search('verification', $rows[0], true);
        // Verified enum (=2) is written as its int value, not the object.
        $this->assertSame('2', $rows[1][$verificationIndex]);
    }

    public function test_object_tag_with_one_material_emits_two_rows()
    {
        $photo = $this->makePhoto();
        $pt = PhotoTag::create([
            'photo_id' => $photo->id,
            'category_id' => $this->alcohol->id,
            'litter_object_id' => $this->bottle->id,
            'category_litter_object_id' => $this->bottleCloId,
            'litter_object_type_id' => $this->spirits->id,
            'quantity' => 7,
        ]);
        PhotoTagExtraTags::create(['photo_tag_id' => $pt->id, 'tag_type' => 'material', 'tag_type_id' => $this->glass->id, 'quantity' => 1]);
        app(GeneratePhotoSummaryService::class)->run($photo->fresh());

        $rows = $this->exportLong()->map(Photo::with(['photoTags.extraTags.extraTag', 'team'])->find($photo->id));

        $this->assertCount(2, $rows, 'bare-object row + 1 material row');
        // Row 0: bare object
        $this->assertEquals('', $rows[0][9]);
        $this->assertEquals(7, $rows[0][12]);
        $this->assertEquals($pt->id, $rows[0][13]);
        // Row 1: material
        $this->assertEquals('glass', $rows[1][9]);
        $this->assertEquals(7, $rows[1][12], 'material row inherits parent qty');
        $this->assertEquals($pt->id, $rows[1][13]);
    }

    public function test_object_tag_with_three_brands_emits_one_bare_plus_three_brand_rows_with_per_brand_qty()
    {
        $coca = BrandList::firstOrCreate(['key' => 'long_coca']);
        $jameson = BrandList::firstOrCreate(['key' => 'long_jameson']);
        $evian = BrandList::firstOrCreate(['key' => 'long_evian']);

        $photo = $this->makePhoto();
        $pt = PhotoTag::create([
            'photo_id' => $photo->id,
            'category_id' => $this->alcohol->id,
            'litter_object_id' => $this->bottle->id,
            'category_litter_object_id' => $this->bottleCloId,
            'litter_object_type_id' => $this->spirits->id,
            'quantity' => 8,
        ]);
        PhotoTagExtraTags::create(['photo_tag_id' => $pt->id, 'tag_type' => 'brand', 'tag_type_id' => $coca->id, 'quantity' => 3]);
        PhotoTagExtraTags::create(['photo_tag_id' => $pt->id, 'tag_type' => 'brand', 'tag_type_id' => $jameson->id, 'quantity' => 3]);
        PhotoTagExtraTags::create(['photo_tag_id' => $pt->id, 'tag_type' => 'brand', 'tag_type_id' => $evian->id, 'quantity' => 2]);
        app(GeneratePhotoSummaryService::class)->run($photo->fresh());

        $rows = $this->exportLong()->map(Photo::with(['photoTags.extraTags.extraTag', 'team'])->find($photo->id));

        $this->assertCount(4, $rows, 'bare-object + 3 brand rows');
        // Bare row
        $this->assertEquals('', $rows[0][10]);
        $this->assertEquals(8, $rows[0][12]);

        // Brand rows — per-brand qty (3, 3, 2)
        $brandRows = array_slice($rows, 1);
        $brandQtys = array_combine(
            array_column($brandRows, 10),
            array_column($brandRows, 12)
        );
        $this->assertEquals(3, $brandQtys['long_coca']);
        $this->assertEquals(3, $brandQtys['long_jameson']);
        $this->assertEquals(2, $brandQtys['long_evian']);

        // All brand rows share the same photo_tag_id
        foreach ($brandRows as $r) {
            $this->assertEquals($pt->id, $r[13]);
        }
    }

    public function test_brand_only_phototag_no_object_emits_no_bare_row()
    {
        $brand = BrandList::firstOrCreate(['key' => 'lone_brand']);

        $photo = $this->makePhoto();

        // Brand-only PhotoTag matches production extras-only shape: category_id,
        // litter_object_id, AND category_litter_object_id all NULL (per AddTagsToPhotoAction::createExtraTagOnly).
        $pt = PhotoTag::create([
            'photo_id' => $photo->id,
            'quantity' => 1,
        ]);
        PhotoTagExtraTags::create(['photo_tag_id' => $pt->id, 'tag_type' => 'brand', 'tag_type_id' => $brand->id, 'quantity' => 5]);
        app(GeneratePhotoSummaryService::class)->run($photo->fresh());

        $rows = $this->exportLong()->map(Photo::with(['photoTags.extraTags.extraTag', 'team'])->find($photo->id));

        $this->assertCount(1, $rows, 'only the brand row, no bare-object row when litter_object_id is null');
        $this->assertEquals('', $rows[0][7], 'object empty');
        $this->assertEquals('lone_brand', $rows[0][10]);
        $this->assertEquals(5, $rows[0][12]);
    }

    public function test_custom_tag_only_phototag_emits_one_row_qty_one_per_custom_tag()
    {
        $ct1 = CustomTagNew::firstOrCreate(['key' => 'long_ct1']);
        $ct2 = CustomTagNew::firstOrCreate(['key' => 'long_ct2']);

        $photo = $this->makePhoto();
        // Extras-only PhotoTag: matches production createExtraTagOnly shape (no CLO/cat/obj).
        $pt = PhotoTag::create(['photo_id' => $photo->id, 'quantity' => 1]);
        PhotoTagExtraTags::create(['photo_tag_id' => $pt->id, 'tag_type' => 'custom_tag', 'tag_type_id' => $ct1->id, 'quantity' => 1]);
        PhotoTagExtraTags::create(['photo_tag_id' => $pt->id, 'tag_type' => 'custom_tag', 'tag_type_id' => $ct2->id, 'quantity' => 1]);
        app(GeneratePhotoSummaryService::class)->run($photo->fresh());

        $rows = $this->exportLong()->map(Photo::with(['photoTags.extraTags.extraTag', 'team'])->find($photo->id));

        $this->assertCount(2, $rows);
        $cts = array_column($rows, 11);
        sort($cts);
        $this->assertEquals(['long_ct1', 'long_ct2'], $cts);
        foreach ($rows as $r) {
            $this->assertEquals(1, $r[12], 'custom_tag rows always qty=1');
        }
    }

    public function test_material_only_phototag_emits_only_material_rows()
    {
        $cardboard = Materials::firstOrCreate(['key' => 'cardboard']);

        $photo = $this->makePhoto();
        // Extras-only PhotoTag: matches production createExtraTagOnly shape (no CLO/cat/obj).
        $pt = PhotoTag::create(['photo_id' => $photo->id, 'quantity' => 2]);
        PhotoTagExtraTags::create(['photo_tag_id' => $pt->id, 'tag_type' => 'material', 'tag_type_id' => $cardboard->id, 'quantity' => 1]);
        app(GeneratePhotoSummaryService::class)->run($photo->fresh());

        $rows = $this->exportLong()->map(Photo::with(['photoTags.extraTags.extraTag', 'team'])->find($photo->id));

        $this->assertCount(1, $rows);
        $this->assertEquals('', $rows[0][7], 'no object');
        $this->assertEquals('cardboard', $rows[0][9]);
        $this->assertEquals(2, $rows[0][12], 'material rows inherit parent qty');
    }

    public function test_photo_with_zero_phototags_emits_zero_rows()
    {
        $photo = $this->makePhoto();

        $rows = $this->exportLong()->map(Photo::with(['photoTags.extraTags.extraTag', 'team'])->find($photo->id));

        $this->assertEquals([], $rows, 'no PhotoTags → no rows; Maatwebsite flat-maps [] to nothing');
    }

    public function test_team_name_populated_in_long_export()
    {
        $team = Team::factory()->create(['name' => 'Iowa Litter Crew', 'leader' => $this->user->id]);
        $photo = $this->makePhoto($team->id);
        PhotoTag::create([
            'photo_id' => $photo->id,
            'category_id' => $this->alcohol->id,
            'litter_object_id' => $this->bottle->id,
            'category_litter_object_id' => $this->bottleCloId,
            'quantity' => 1,
        ]);
        app(GeneratePhotoSummaryService::class)->run($photo->fresh());

        $rows = $this->exportLong()->map(Photo::with(['photoTags.extraTags.extraTag', 'team'])->find($photo->id));

        $this->assertEquals('Iowa Litter Crew', $rows[0][4]);
    }

    public function test_layout_long_ignores_format_param()
    {
        $photo = $this->makePhoto();
        PhotoTag::create([
            'photo_id' => $photo->id,
            'category_id' => $this->alcohol->id,
            'litter_object_id' => $this->bottle->id,
            'category_litter_object_id' => $this->bottleCloId,
            'litter_object_type_id' => $this->spirits->id,
            'quantity' => 1,
        ]);
        app(GeneratePhotoSummaryService::class)->run($photo->fresh());

        // format=split,joined should be silently ignored when layout=long
        $exportWithFormats = new CreateCSVExport(null, null, null, $this->user->id, [], [], ['split', 'joined'], 'long');
        $exportPlain = $this->exportLong();

        $this->assertEquals($exportPlain->headings(), $exportWithFormats->headings());
        $this->assertCount(14, $exportWithFormats->headings());
        $this->assertNotContains('ALCOHOL', $exportWithFormats->headings());
        $this->assertNotContains('TYPES', $exportWithFormats->headings());
        $this->assertNotContains('spirits_bottle', $exportWithFormats->headings());
    }

    public function test_parseLayout_validates_to_wide_or_long()
    {
        $this->assertEquals('wide', CreateCSVExport::parseLayout(null));
        $this->assertEquals('wide', CreateCSVExport::parseLayout(''));
        $this->assertEquals('wide', CreateCSVExport::parseLayout('wide'));
        $this->assertEquals('wide', CreateCSVExport::parseLayout('WIDE'));
        $this->assertEquals('wide', CreateCSVExport::parseLayout('  Wide  '));
        $this->assertEquals('wide', CreateCSVExport::parseLayout('garbage'));
        $this->assertEquals('wide', CreateCSVExport::parseLayout('LONGISH'));

        $this->assertEquals('long', CreateCSVExport::parseLayout('long'));
        $this->assertEquals('long', CreateCSVExport::parseLayout('LONG'));
        $this->assertEquals('long', CreateCSVExport::parseLayout('  Long  '));
    }

    public function test_dedup_by_photo_tag_id_recovers_parent_quantity()
    {
        // Object PhotoTag with 2 materials, qty=5. Cartesian-style summing all rows
        // gives 5 + 5 + 5 = 15 (overcount). Filtering to material='' (the bare-object row)
        // gives 5 — the true parent qty.
        $plastic = Materials::firstOrCreate(['key' => 'plastic']);

        $photo = $this->makePhoto();
        $pt = PhotoTag::create([
            'photo_id' => $photo->id,
            'category_id' => $this->alcohol->id,
            'litter_object_id' => $this->bottle->id,
            'category_litter_object_id' => $this->bottleCloId,
            'litter_object_type_id' => $this->spirits->id,
            'quantity' => 5,
        ]);
        PhotoTagExtraTags::create(['photo_tag_id' => $pt->id, 'tag_type' => 'material', 'tag_type_id' => $this->glass->id, 'quantity' => 1]);
        PhotoTagExtraTags::create(['photo_tag_id' => $pt->id, 'tag_type' => 'material', 'tag_type_id' => $plastic->id, 'quantity' => 1]);
        app(GeneratePhotoSummaryService::class)->run($photo->fresh());

        $rows = $this->exportLong()->map(Photo::with(['photoTags.extraTags.extraTag', 'team'])->find($photo->id));

        $this->assertCount(3, $rows, 'bare + 2 materials');

        // Naive sum overcounts
        $sumAll = array_sum(array_column($rows, 12));
        $this->assertEquals(15, $sumAll, 'naive SUM(quantity) overcounts: 5 (bare) + 5 (glass) + 5 (plastic)');

        // Dedup by filtering to the bare-object row (material/brand/custom_tag all empty) recovers parent qty
        $bareRows = array_filter($rows, fn ($r) => $r[9] === '' && $r[10] === '' && $r[11] === '');
        $this->assertCount(1, $bareRows);
        $this->assertEquals(5, array_values($bareRows)[0][12]);
    }
}
