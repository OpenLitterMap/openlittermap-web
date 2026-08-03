<?php

namespace Tests\Feature\Migration;

use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CategoryObject;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\Materials;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Litter\Tags\PhotoTagExtraTags;
use App\Models\Photo;
use App\Models\Users\User;
use Database\Seeders\Tags\GenerateTagsSeeder;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MigrateTagTest extends TestCase
{
    private const QUEUE = 'storage/framework/testing/migrate-tag-queue.csv';

    private Category $category;
    private LitterObject $source;
    private LitterObject $target;
    private CategoryObject $targetClo;
    private Photo $photo;
    private PhotoTag $tag;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GenerateTagsSeeder::class);
        Storage::fake('local');

        $this->category = Category::where('key', 'other')->firstOrFail();
        $this->target = LitterObject::where('key', 'plastic_bag')->firstOrFail();
        $this->targetClo = CategoryObject::where('category_id', $this->category->id)
            ->where('litter_object_id', $this->target->id)
            ->firstOrFail();

        // The historical object: exists, holds tags, has no pivot of its own.
        $this->source = LitterObject::firstOrCreate(['key' => 'plasticBags'], ['crowdsourced' => true]);

        $user = User::factory()->create();
        $this->photo = Photo::factory()->create(['verified' => 2, 'user_id' => $user->id]);

        $this->tag = PhotoTag::create([
            'photo_id' => $this->photo->id,
            'category_id' => $this->category->id,
            'litter_object_id' => $this->source->id,
            'category_litter_object_id' => null,
            'quantity' => 7,
        ]);

        // Production photos all carry a computed summary and XP; settle them so the
        // migration's "XP must not move" check measures the rename, not a first computation.
        $this->photo->generateSummary();
        $this->photo->refresh();

        $this->writeQueue('IDENTIFIED');
    }

    protected function tearDown(): void
    {
        @unlink(base_path(self::QUEUE));
        parent::tearDown();
    }

    private function writeQueue(string $status, array $overrides = []): void
    {
        $row = array_merge([
            'entry_id' => 'other--plasticBags',
            'status' => $status,
            'source_category_key' => 'other',
            'source_category_id' => $this->category->id,
            'source_object_key' => 'plasticBags',
            'source_object_id' => $this->source->id,
            'source_type_key' => '',
            'source_type_id' => '',
            'target_category_key' => 'other',
            'target_category_id' => $this->category->id,
            'target_object_key' => 'plastic_bag',
            'target_object_id' => $this->target->id,
            'target_clo_id' => $this->targetClo->id,
            'target_type_key' => '',
            'target_type_id' => '',
            'expected_rows' => 1,
            'expected_items' => 7,
            'expected_photos' => 1,
            'collision_groups' => '',
            'affected_code' => 'BrandsConfig; translations',
            'approver' => '',
            'approved_at' => '',
            'commit' => '',
            'verification_evidence' => '',
            'notes' => '',
        ], $overrides);

        @mkdir(dirname(base_path(self::QUEUE)), 0777, true);
        $fh = fopen(base_path(self::QUEUE), 'w');
        fputcsv($fh, array_keys($row));
        fputcsv($fh, array_values($row));
        fclose($fh);
    }

    private function migrate(array $opts = []): \Illuminate\Testing\PendingCommand
    {
        return $this->artisan('olm:migrate-tag', array_merge([
            '--entry' => 'other--plasticBags',
            '--queue' => self::QUEUE,
        ], $opts));
    }

    // ── Gating ───────────────────────────────────────────────────────────────

    public function test_apply_is_blocked_until_code_updated(): void
    {
        foreach (['IDENTIFIED', 'MAPPING_APPROVED'] as $status) {
            $this->writeQueue($status);
            $this->migrate(['--apply' => true])->assertExitCode(1);
        }

        $this->assertEquals($this->source->id, $this->tag->fresh()->litter_object_id, 'no data may be written');
    }

    public function test_status_transitions_must_be_sequential(): void
    {
        $this->migrate(['--advance' => 'LOCAL_APPLIED', '--by' => 'tester'])->assertExitCode(1);
        $this->migrate(['--advance' => 'MAPPING_APPROVED', '--by' => 'tester'])->assertExitCode(0);
    }

    public function test_advance_requires_an_author(): void
    {
        $this->migrate(['--advance' => 'MAPPING_APPROVED'])->assertExitCode(1);
    }

    public function test_verified_transitions_require_evidence(): void
    {
        $this->writeQueue('LOCAL_APPLIED');

        $this->migrate(['--advance' => 'LOCAL_VERIFIED', '--by' => 'tester'])->assertExitCode(1);

        $this->migrate([
            '--advance' => 'LOCAL_VERIFIED', '--by' => 'tester', '--evidence' => 'export + api checked',
        ])->assertExitCode(0);
    }

    public function test_unknown_entry_fails(): void
    {
        $this->artisan('olm:migrate-tag', ['--entry' => 'nope', '--queue' => self::QUEUE])
            ->assertExitCode(1);
    }

    public function test_expectation_mismatch_aborts(): void
    {
        $this->writeQueue('CODE_UPDATED', ['expected_rows' => 999]);

        $this->migrate(['--apply' => true])->assertExitCode(1);

        $this->assertEquals($this->source->id, $this->tag->fresh()->litter_object_id);
    }

    public function test_wrong_target_clo_aborts(): void
    {
        $otherClo = CategoryObject::where('id', '!=', $this->targetClo->id)->firstOrFail();
        $this->writeQueue('CODE_UPDATED', ['target_clo_id' => $otherClo->id]);

        $this->migrate(['--apply' => true])->assertExitCode(1);
    }

    // ── Transformation ───────────────────────────────────────────────────────

    public function test_apply_repoints_the_pair_and_preserves_everything_else(): void
    {
        $material = Materials::firstOrFail();
        PhotoTagExtraTags::create([
            'photo_tag_id' => $this->tag->id,
            'tag_type' => 'material',
            'tag_type_id' => $material->id,
            'quantity' => 1,
        ]);

        // Settle the summary for the added extra so the migration measures only the rename.
        $this->photo->generateSummary();
        $this->photo->refresh();

        $this->writeQueue('CODE_UPDATED');
        $this->migrate(['--apply' => true])->assertExitCode(0);

        $after = $this->tag->fresh();

        $this->assertEquals($this->target->id, $after->litter_object_id, 'object must move to the target');
        $this->assertEquals($this->targetClo->id, $after->category_litter_object_id, 'CLO must be set');
        $this->assertEquals($this->category->id, $after->category_id, 'category must not change');
        $this->assertNull($after->litter_object_type_id, 'type must not change');
        $this->assertEquals(7, $after->quantity, 'quantity must not change');

        $this->assertSame(
            1,
            PhotoTagExtraTags::where('photo_tag_id', $this->tag->id)->count(),
            'extras must survive the migration'
        );
    }

    public function test_source_object_survives_as_a_tombstone(): void
    {
        $this->writeQueue('CODE_UPDATED');
        $this->migrate(['--apply' => true])->assertExitCode(0);

        $this->assertDatabaseHas('litter_objects', ['id' => $this->source->id, 'key' => 'plasticBags']);
        $this->assertSame(
            0,
            CategoryObject::where('litter_object_id', $this->source->id)->count(),
            'the historical pair must never gain a pivot'
        );
    }

    public function test_colliding_rows_are_never_merged(): void
    {
        // An existing canonical tag on the same photo that the migrated row will collide with.
        PhotoTag::create([
            'photo_id' => $this->photo->id,
            'category_id' => $this->category->id,
            'litter_object_id' => $this->target->id,
            'category_litter_object_id' => $this->targetClo->id,
            'quantity' => 3,
        ]);

        // Settle the summary/XP for the added row so the migration measures only the rename.
        $this->photo->generateSummary();
        $this->photo->refresh();

        $this->writeQueue('CODE_UPDATED');
        $this->migrate(['--apply' => true])->assertExitCode(0);

        $rows = PhotoTag::where('photo_id', $this->photo->id)
            ->where('litter_object_id', $this->target->id)
            ->get();

        $this->assertCount(2, $rows, 'colliding rows must stay physically separate');
        $this->assertEquals(10, $rows->sum('quantity'), 'total quantity must be preserved');
    }

    public function test_summary_reflects_the_new_object(): void
    {
        $this->writeQueue('CODE_UPDATED');
        $this->migrate(['--apply' => true])->assertExitCode(0);

        $summary = $this->photo->fresh()->summary;
        $objectIds = collect($summary['tags'] ?? [])->pluck('object_id')->all();

        $this->assertContains($this->target->id, $objectIds, 'summary must point at the target object');
        $this->assertNotContains($this->source->id, $objectIds, 'summary must not retain the source object');
    }

    public function test_xp_does_not_move(): void
    {
        $xpBefore = $this->photo->fresh()->xp;

        $this->writeQueue('CODE_UPDATED');
        $this->migrate(['--apply' => true])->assertExitCode(0);

        $this->assertEquals($xpBefore, $this->photo->fresh()->xp, 'a rename must not change XP');
    }

    public function test_rerun_after_completion_is_a_clean_no_op(): void
    {
        $this->writeQueue('CODE_UPDATED');
        $this->migrate(['--apply' => true])->assertExitCode(0);

        $qty = PhotoTag::where('litter_object_id', $this->target->id)->sum('quantity');

        // Source pair is drained, so expectations no longer match — must refuse, not re-migrate.
        $this->migrate(['--apply' => true])->assertExitCode(1);

        $this->assertEquals($qty, PhotoTag::where('litter_object_id', $this->target->id)->sum('quantity'));
    }

    public function test_there_is_no_bulk_apply(): void
    {
        $this->assertStringNotContainsString(
            '--all',
            (new \App\Console\Commands\tmp\v5\PostMigAug2026\MigrateTag())->getDefinition()->getSynopsis(),
            'bulk apply must not exist — migrations are one entry at a time'
        );
    }
}
