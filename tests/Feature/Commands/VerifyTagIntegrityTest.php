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
