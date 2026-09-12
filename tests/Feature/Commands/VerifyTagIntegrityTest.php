<?php

namespace Tests\Feature\Commands;

use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CategoryObject;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Photo;
use Database\Seeders\Tags\GenerateTagsSeeder;
use Tests\TestCase;

/**
 * The command predates the deprecation of `photo_tags.category_litter_object_id`. Its checks used
 * to treat that column as authoritative; the pairing (`category_id`, `litter_object_id`) is the
 * source of truth now, so a null pointer is not a defect and must not be reported or "repaired".
 */
class VerifyTagIntegrityTest extends TestCase
{
    private Category $category;
    private Photo $photo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GenerateTagsSeeder::class);

        $this->category = Category::where('key', 'other')->firstOrFail();
        $this->photo = Photo::factory()->create();
    }

    /**
     * A mapping that stopped part-way leaves rows on a pairing whose pivot already records its
     * survivor. The pairing is sanctioned, so the pivot check passes, yet the migration is not
     * complete. The deployment gate must fail until the mapping is re-run.
     */
    public function test_it_reports_rows_left_on_a_tombstoned_pairing(): void
    {
        [$source, $target] = $this->tombstonedPairing();

        PhotoTag::create([
            'photo_id' => $this->photo->id,
            'category_id' => $source->category_id,
            'litter_object_id' => $source->litter_object_id,
            'category_litter_object_id' => $source->id,
            'quantity' => 1,
        ]);

        $this->artisan('olm:verify-tag-integrity')
            ->expectsOutputToContain('tombstoned')
            ->assertExitCode(1);
    }

    public function test_it_reports_quick_tags_left_on_a_tombstoned_pairing(): void
    {
        [$source, $target] = $this->tombstonedPairing();

        \Illuminate\Support\Facades\DB::table('user_quick_tags')->insert([
            'user_id' => \App\Models\Users\User::factory()->create()->id,
            'clo_id' => $source->id,
            'quantity' => 1,
            'materials' => '[]',
            'brands' => '[]',
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('olm:verify-tag-integrity')
            ->expectsOutputToContain('quick tag')
            ->assertExitCode(1);
    }

    public function test_it_reports_a_retirement_cycle(): void
    {
        [$source, $target] = $this->tombstonedPairing();
        $target->update(['merged_into_clo_id' => $source->id]);

        $this->artisan('olm:verify-tag-integrity')
            ->expectsOutputToContain('cycle')
            ->assertExitCode(1);
    }

    /** @return array{0: CategoryObject, 1: CategoryObject} retired source pairing, replacement pairing */
    private function tombstonedPairing(): array
    {
        $source = CategoryObject::create([
            'category_id' => $this->category->id,
            'litter_object_id' => LitterObject::create(['key' => 'tombstoned_object'])->id,
        ]);
        $target = CategoryObject::where('category_id', $this->category->id)->firstOrFail();
        $source->update(['merged_into_clo_id' => $target->id]);
        CategoryObject::flushResolverCache();

        return [$source, $target];
    }

    /**
     * A typed row on a pairing with no pivot is already reported as "needs a taxonomy decision".
     * Judging its type against a pivot that does not exist yet would clear a type the eventual
     * pairing may well approve — a repair run before the seeder would destroy data.
     */
    public function test_fix_does_not_clear_the_type_on_a_pairing_that_has_no_pivot(): void
    {
        $orphan = LitterObject::create(['key' => 'shadowObject']);
        $type = \App\Models\Litter\Tags\LitterObjectType::firstOrCreate(['key' => 'water']);
        $tag = PhotoTag::create([
            'photo_id' => $this->photo->id,
            'category_id' => $this->category->id,
            'litter_object_id' => $orphan->id,
            'litter_object_type_id' => $type->id,
            'quantity' => 1,
        ]);

        $this->artisan('olm:verify-tag-integrity', ['--fix' => true])
            ->expectsOutputToContain('Type references: OK')
            ->assertExitCode(1);

        $this->assertSame($type->id, $tag->fresh()->litter_object_type_id, 'type must survive until the pairing is decided');
    }

    public function test_photo_id_scoping_ignores_global_checks(): void
    {
        [$source, $target] = $this->tombstonedPairing();
        $target->update(['merged_into_clo_id' => $source->id]);

        $this->artisan('olm:verify-tag-integrity', ['--photo-id' => $this->photo->id])
            ->assertExitCode(0);
    }

    public function test_a_null_pointer_on_a_sanctioned_pairing_is_not_an_integrity_error(): void
    {
        $clo = CategoryObject::where('category_id', $this->category->id)->firstOrFail();

        PhotoTag::create([
            'photo_id' => $this->photo->id,
            'category_id' => $clo->category_id,
            'litter_object_id' => $clo->litter_object_id,
            'category_litter_object_id' => null,
            'quantity' => 3,
        ]);

        $this->artisan('olm:verify-tag-integrity')
            ->expectsOutputToContain('0 issues found')
            ->assertExitCode(0);
    }

    public function test_extra_tag_only_rows_are_not_integrity_errors(): void
    {
        PhotoTag::create([
            'photo_id' => $this->photo->id,
            'category_id' => null,
            'litter_object_id' => null,
            'category_litter_object_id' => null,
            'quantity' => 1,
        ]);

        $this->artisan('olm:verify-tag-integrity')
            ->expectsOutputToContain('0 issues found')
            ->assertExitCode(0);
    }

    public function test_it_reports_a_pairing_the_taxonomy_does_not_sanction(): void
    {
        $orphan = LitterObject::create(['key' => 'shadowObject']);

        PhotoTag::create([
            'photo_id' => $this->photo->id,
            'category_id' => $this->category->id,
            'litter_object_id' => $orphan->id,
            'category_litter_object_id' => null,
            'quantity' => 2,
        ]);

        $this->artisan('olm:verify-tag-integrity')
            ->expectsOutputToContain('no CLO pivot')
            ->assertExitCode(1);
    }

    /**
     * `--fix` cannot invent a pivot, so an unsanctioned pairing survives the repair. Returning
     * success anyway makes this a deployment check that exits 0 with defects present — worse than
     * no check. It must fail when anything remains after repairing.
     */
    public function test_fix_still_fails_when_unrepairable_defects_remain(): void
    {
        $orphan = LitterObject::create(['key' => 'shadowObject']);

        PhotoTag::create([
            'photo_id' => $this->photo->id,
            'category_id' => $this->category->id,
            'litter_object_id' => $orphan->id,
            'category_litter_object_id' => null,
            'quantity' => 2,
        ]);

        $this->artisan('olm:verify-tag-integrity', ['--fix' => true])
            ->expectsOutputToContain('no CLO pivot')
            ->assertExitCode(1);
    }

    /**
     * The repair direction is the whole point: the pointer is rebuilt from the pairing. Repairing
     * the other way round would overwrite the source of truth from a deprecated column.
     */
    public function test_fix_repairs_the_stale_pointer_and_leaves_the_pairing_alone(): void
    {
        $clo = CategoryObject::where('category_id', $this->category->id)->firstOrFail();
        $wrongClo = CategoryObject::where('category_id', $this->category->id)
            ->where('id', '!=', $clo->id)
            ->firstOrFail();

        $tag = PhotoTag::create([
            'photo_id' => $this->photo->id,
            'category_id' => $clo->category_id,
            'litter_object_id' => $clo->litter_object_id,
            'category_litter_object_id' => $wrongClo->id,
            'quantity' => 1,
        ]);

        $this->artisan('olm:verify-tag-integrity', ['--fix' => true])->assertExitCode(0);

        $tag->refresh();

        $this->assertSame($clo->id, $tag->category_litter_object_id);
        $this->assertSame($clo->category_id, $tag->category_id);
        $this->assertSame($clo->litter_object_id, $tag->litter_object_id);
    }
}
