<?php

namespace Tests\Feature\Exports;

use App\Exports\CreateCSVExport;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CategoryObject;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\LitterObjectType;
use App\Models\Litter\Tags\Materials;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Litter\Tags\PhotoTagExtraTags;
use App\Models\Photo;
use App\Models\Users\User;
use App\Services\Tags\GeneratePhotoSummaryService;
use Tests\TestCase;

/**
 * `format` query param toggles between v5 split layout (object/type/material in
 * separate columns) and v4-style joined layout ({type}_{object} columns).
 *
 * Spec: `format=split`, `format=joined`, `format=split,joined`, empty/missing
 * defaults to split. Joined-only mode suppresses the split block AND the TYPES
 * block (the type dimension is folded into the joined column key).
 */
class CreateCSVExportFormatTest extends TestCase
{
    private User $user;
    private Photo $fireballPhoto;
    private Category $alcohol;
    private LitterObject $bottle;
    private LitterObjectType $spirits;
    private Materials $glass;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alcohol = Category::firstOrCreate(['key' => 'alcohol']);
        $this->bottle = LitterObject::firstOrCreate(['key' => 'bottle']);
        $this->spirits = LitterObjectType::firstOrCreate(['key' => 'spirits'], ['name' => 'Spirits']);
        $this->glass = Materials::firstOrCreate(['key' => 'glass']);

        $bottleCloId = CategoryObject::firstOrCreate([
            'category_id' => $this->alcohol->id,
            'litter_object_id' => $this->bottle->id,
        ])->id;

        $this->user = User::factory()->create();
        $this->fireballPhoto = Photo::factory()->create([
            'verified' => 2,
            'user_id' => $this->user->id,
            'datetime' => now()->toDateTimeString(),
            'lat' => 42.0,
            'lon' => 42.0,
            'address_array' => ['country' => 'Ireland'],
            'remaining' => false,
            'migrated_at' => now(),
        ]);

        $pt = PhotoTag::create([
            'photo_id' => $this->fireballPhoto->id,
            'category_id' => $this->alcohol->id,
            'litter_object_id' => $this->bottle->id,
            'category_litter_object_id' => $bottleCloId,
            'litter_object_type_id' => $this->spirits->id,
            'quantity' => 1,
            'picked_up' => true,
        ]);
        PhotoTagExtraTags::create([
            'photo_tag_id' => $pt->id,
            'tag_type' => 'material',
            'tag_type_id' => $this->glass->id,
            'quantity' => 1,
        ]);

        app(GeneratePhotoSummaryService::class)->run($this->fireballPhoto->fresh());
    }

    public function test_split_format_emits_object_type_material_columns_and_no_joined_column()
    {
        $export = new CreateCSVExport(null, null, null, $this->user->id, [], [], ['split']);
        $headings = $export->headings();
        $mapped = $export->map($this->fireballPhoto->fresh());

        $this->assertContains('bottle', $headings);
        $this->assertContains('spirits', $headings);
        $this->assertContains('glass', $headings);
        $this->assertNotContains('spirits_bottle', $headings);

        $this->assertEquals(1, $mapped[array_search('bottle', $headings)]);
        $this->assertEquals(1, $mapped[array_search('spirits', $headings)]);
        $this->assertEquals(1, $mapped[array_search('glass', $headings)]);
    }

    public function test_joined_format_emits_combined_column_no_split_block_no_types_block_keeps_materials()
    {
        $export = new CreateCSVExport(null, null, null, $this->user->id, [], [], ['joined']);
        $headings = $export->headings();
        $mapped = $export->map($this->fireballPhoto->fresh());

        $this->assertContains('spirits_bottle', $headings);
        $this->assertNotContains('TYPES', $headings, 'TYPES block must be suppressed in joined-only mode');
        $this->assertNotContains('spirits', $headings, 'standalone spirits column must be absent');

        // The split block's per-category object column ("bottle") is suppressed.
        // ALCOHOL header still appears as a separator for the joined block — that's expected.
        $bottleIndex = array_search('bottle', $headings);
        $this->assertFalse(
            $bottleIndex,
            'Bare "bottle" object column from split block must not appear in joined-only mode'
        );

        // MATERIALS block is preserved
        $this->assertContains('MATERIALS', $headings);
        $this->assertContains('glass', $headings);
        $this->assertEquals(1, $mapped[array_search('glass', $headings)]);
        $this->assertEquals(1, $mapped[array_search('spirits_bottle', $headings)]);
    }

    public function test_split_and_joined_format_emits_both_blocks_with_materials_once()
    {
        $export = new CreateCSVExport(null, null, null, $this->user->id, [], [], ['split', 'joined']);
        $headings = $export->headings();
        $mapped = $export->map($this->fireballPhoto->fresh());

        $this->assertContains('spirits_bottle', $headings);
        $this->assertContains('spirits', $headings, 'TYPES.spirits column from split block');
        $this->assertContains('glass', $headings);
        $this->assertEquals(1, count(array_keys($headings, 'glass')), 'glass column appears once');
        $this->assertEquals(1, count(array_keys($headings, 'MATERIALS')), 'MATERIALS separator appears once');

        // Ordering: split block (incl. ALCOHOL section + TYPES block) sits before the joined block
        $splitAlcoholIndex = array_search('ALCOHOL', $headings);
        $typesIndex = array_search('TYPES', $headings);
        $joinedSpiritsBottleIndex = array_search('spirits_bottle', $headings);
        $this->assertNotFalse($splitAlcoholIndex);
        $this->assertNotFalse($typesIndex);
        $this->assertLessThan($joinedSpiritsBottleIndex, $typesIndex, 'TYPES block appears before joined block');

        // Bare bottle column (split block) must equal 1; joined spirits_bottle must also equal 1 — same data, two presentations.
        $bottleIndex = array_search('bottle', $headings);
        $this->assertEquals(1, $mapped[$bottleIndex]);
        $this->assertEquals(1, $mapped[$joinedSpiritsBottleIndex]);
    }

    public function test_empty_format_string_defaults_to_split()
    {
        $normalized = CreateCSVExport::normalizeFormats(array_filter(explode(',', '')));
        $this->assertEquals(['split'], $normalized);

        // Garbage input also defaults
        $this->assertEquals(['split'], CreateCSVExport::normalizeFormats(['nonsense']));
        $this->assertEquals(['split'], CreateCSVExport::normalizeFormats([]));

        // Dedupes
        $this->assertEquals(['split', 'joined'], CreateCSVExport::normalizeFormats(['split', 'split', 'joined']));
        // Trims + lowercases
        $this->assertEquals(['joined', 'split'], CreateCSVExport::normalizeFormats(['  Joined ', 'SPLIT']));
    }

    public function test_object_with_no_type_uses_bare_object_key_in_joined_block()
    {
        // Smoking butts: no litter_object_type_id → joined column should be "butts", not "_butts"
        $smoking = Category::firstOrCreate(['key' => 'smoking']);
        $butts = LitterObject::firstOrCreate(['key' => 'butts']);
        $cloId = CategoryObject::firstOrCreate([
            'category_id' => $smoking->id,
            'litter_object_id' => $butts->id,
        ])->id;

        $photo = Photo::factory()->create([
            'verified' => 2,
            'user_id' => $this->user->id,
            'datetime' => now()->toDateTimeString(),
            'lat' => 42.0,
            'lon' => 42.0,
            'address_array' => ['country' => 'Ireland'],
        ]);
        $pt = PhotoTag::create([
            'photo_id' => $photo->id,
            'category_id' => $smoking->id,
            'litter_object_id' => $butts->id,
            'category_litter_object_id' => $cloId,
            'litter_object_type_id' => null,
            'quantity' => 5,
        ]);
        app(GeneratePhotoSummaryService::class)->run($photo->fresh());

        // Scope to the smoking-butts user only — drop the Fireball photo from the export
        $loneUser = User::factory()->create();
        $photo->update(['user_id' => $loneUser->id]);

        $export = new CreateCSVExport(null, null, null, $loneUser->id, [], [], ['joined']);
        $headings = $export->headings();
        $mapped = $export->map($photo->fresh());

        $this->assertContains('butts', $headings, 'Bare object key when type is null');
        $this->assertNotContains('_butts', $headings, 'Underscore prefix indicates a missing type — must not appear');
        $this->assertEquals(5, $mapped[array_search('butts', $headings)]);
    }

    public function test_joined_block_is_emitted_per_category_so_same_object_under_two_categories_does_not_collide()
    {
        // Spec example: bare "bottle" exists under both ALCOHOL and SOFTDRINKS.
        // Per-category headers must disambiguate (ALCOHOL.bottle vs SOFTDRINKS.bottle visually).
        $softdrinks = Category::firstOrCreate(['key' => 'softdrinks']);
        $cloId = CategoryObject::firstOrCreate([
            'category_id' => $softdrinks->id,
            'litter_object_id' => $this->bottle->id,
        ])->id;

        $sodaPhoto = Photo::factory()->create([
            'verified' => 2,
            'user_id' => $this->user->id,
            'datetime' => now()->toDateTimeString(),
            'lat' => 42.0,
            'lon' => 42.0,
            'address_array' => ['country' => 'Ireland'],
        ]);
        PhotoTag::create([
            'photo_id' => $sodaPhoto->id,
            'category_id' => $softdrinks->id,
            'litter_object_id' => $this->bottle->id,
            'category_litter_object_id' => $cloId,
            'litter_object_type_id' => null, // bare "bottle" softdrinks (collision case)
            'quantity' => 2,
        ]);
        app(GeneratePhotoSummaryService::class)->run($sodaPhoto->fresh());

        $export = new CreateCSVExport(null, null, null, $this->user->id, [], [], ['joined']);
        $headings = $export->headings();

        // Two ALCOHOL/SOFTDRINKS sub-headers separated by "bottle" / "spirits_bottle" entries.
        $alcoholIndex = array_search('ALCOHOL', $headings);
        $softdrinksIndex = array_search('SOFTDRINKS', $headings);
        $this->assertNotFalse($alcoholIndex);
        $this->assertNotFalse($softdrinksIndex);

        // Two "bottle" columns appear — one per category — disambiguated only by their position
        // relative to the ALCOHOL / SOFTDRINKS section markers (same pattern as the split block).
        $bottlePositions = array_keys($headings, 'bottle');
        $this->assertCount(1, $bottlePositions, 'ALCOHOL has "spirits_bottle" not bare "bottle"; SOFTDRINKS has bare "bottle"');
        $spiritsBottlePositions = array_keys($headings, 'spirits_bottle');
        $this->assertCount(1, $spiritsBottlePositions);

        // ALCOHOL section ends before SOFTDRINKS section begins.
        $this->assertLessThan($softdrinksIndex, $spiritsBottlePositions[0]);
        $this->assertGreaterThan($softdrinksIndex, $bottlePositions[0]);

        $mapped = $export->map($sodaPhoto->fresh());
        $this->assertEquals(2, $mapped[$bottlePositions[0]], 'SOFTDRINKS bottle slot reflects soda photo qty');
        $this->assertNull($mapped[$spiritsBottlePositions[0]], 'soda photo has nothing in ALCOHOL.spirits_bottle');
    }

    public function test_joined_block_does_not_collapse_materials_into_object_columns()
    {
        // Photo with object + multiple materials. Joined column must be one entry
        // ({type}_{object}); MATERIALS block carries each material independently.
        $cup = LitterObject::firstOrCreate(['key' => 'cup']);
        $cloId = CategoryObject::firstOrCreate([
            'category_id' => $this->alcohol->id,
            'litter_object_id' => $cup->id,
        ])->id;
        $plastic = Materials::firstOrCreate(['key' => 'plastic']);
        $paper = Materials::firstOrCreate(['key' => 'paper']);

        $photo = Photo::factory()->create([
            'verified' => 2,
            'user_id' => $this->user->id,
            'datetime' => now()->toDateTimeString(),
            'lat' => 42.0,
            'lon' => 42.0,
            'address_array' => ['country' => 'Ireland'],
        ]);
        $pt = PhotoTag::create([
            'photo_id' => $photo->id,
            'category_id' => $this->alcohol->id,
            'litter_object_id' => $cup->id,
            'category_litter_object_id' => $cloId,
            'litter_object_type_id' => null,
            'quantity' => 4,
        ]);
        PhotoTagExtraTags::create(['photo_tag_id' => $pt->id, 'tag_type' => 'material', 'tag_type_id' => $plastic->id, 'quantity' => 1]);
        PhotoTagExtraTags::create(['photo_tag_id' => $pt->id, 'tag_type' => 'material', 'tag_type_id' => $paper->id, 'quantity' => 1]);

        app(GeneratePhotoSummaryService::class)->run($photo->fresh());

        // Drop fireball + photo isolation to a new user
        $loneUser = User::factory()->create();
        $photo->update(['user_id' => $loneUser->id]);

        $export = new CreateCSVExport(null, null, null, $loneUser->id, [], [], ['joined']);
        $headings = $export->headings();
        $mapped = $export->map($photo->fresh());

        // One joined column for the cup
        $this->assertContains('cup', $headings);
        $this->assertEquals(4, $mapped[array_search('cup', $headings)]);

        // Both materials present in the MATERIALS block
        $this->assertContains('plastic', $headings);
        $this->assertContains('paper', $headings);
        $this->assertEquals(4, $mapped[array_search('plastic', $headings)]);
        $this->assertEquals(4, $mapped[array_search('paper', $headings)]);

        // No combined material_object key like "plastic_cup"
        $this->assertNotContains('plastic_cup', $headings);
        $this->assertNotContains('paper_cup', $headings);
    }
}
