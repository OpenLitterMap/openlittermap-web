<?php

namespace Tests\Feature\Tags;

use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Litter\Tags\PhotoTagExtraTags;
use App\Models\Photo;
use Database\Seeders\Tags\GenerateTagsSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CleanupOrphanedObjectsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GenerateTagsSeeder::class);
    }

    public function test_it_reports_no_orphans_when_clean(): void
    {
        $this->artisan('olm:cleanup-orphaned-objects')
            ->assertSuccessful()
            ->expectsOutputToContain('No orphaned data found.');
    }

    public function test_it_detects_orphaned_litter_objects(): void
    {
        // Create a litter_object not referenced by any CLO
        $orphan = LitterObject::create(['key' => 'orphan_test_object']);

        $this->artisan('olm:cleanup-orphaned-objects')
            ->assertSuccessful()
            ->expectsOutputToContain('Orphaned litter_objects (no CLO reference)');

        // Cleanup
        $orphan->delete();
    }

    public function test_fix_mode_deletes_orphaned_litter_objects(): void
    {
        $orphan = LitterObject::create(['key' => 'orphan_to_delete']);

        $this->artisan('olm:cleanup-orphaned-objects', ['--fix' => true])
            ->assertSuccessful();

        $this->assertNull(LitterObject::find($orphan->id));
    }

    public function test_it_detects_dangling_extra_tags(): void
    {
        $photo = Photo::factory()->create();

        $cloId = DB::table('category_litter_object')->first()->id;

        $photoTag = PhotoTag::create([
            'photo_id' => $photo->id,
            'category_litter_object_id' => $cloId,
            'quantity' => 1,
        ]);

        // Create an extra_tag with a non-existent tag_type_id
        PhotoTagExtraTags::create([
            'photo_tag_id' => $photoTag->id,
            'tag_type' => 'material',
            'tag_type_id' => 999999,
            'quantity' => 1,
        ]);

        $this->artisan('olm:cleanup-orphaned-objects')
            ->assertSuccessful()
            ->expectsOutputToContain('Dangling extra_tags');
    }
}
