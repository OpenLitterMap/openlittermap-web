<?php

namespace Tests\Feature\Migration;

use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CategoryObject;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Photo;
use App\Models\Users\User;
use Database\Seeders\Tags\GenerateTagsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RepairTagPivotTest extends TestCase
{
    private Category $category;
    private LitterObject $legacy;
    private Photo $photo;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GenerateTagsSeeder::class);

        // Recovery state is real files on the local disk — isolate it so a leftover state
        // file from one test can never leak into another (or into the developer's storage).
        Storage::fake('local');

        $this->category = Category::where('key', 'other')->firstOrFail();
        $this->legacy = LitterObject::firstOrCreate(['key' => 'plasticBags'], ['crowdsourced' => true]);

        $this->user = User::factory()->create();
        $this->photo = Photo::factory()->create(['verified' => 2, 'user_id' => $this->user->id]);

        PhotoTag::create([
            'photo_id' => $this->photo->id,
            'category_id' => $this->category->id,
            'litter_object_id' => $this->legacy->id,
            'category_litter_object_id' => null,
            'quantity' => 7,
        ]);
    }

    private function orphanCount(): int
    {
        return PhotoTag::where('litter_object_id', $this->legacy->id)
            ->whereNull('category_litter_object_id')
            ->count();
    }

    private function repair(array $opts = []): \Illuminate\Testing\PendingCommand
    {
        return $this->artisan('olm:repair-tag-pivot', array_merge(['--object' => 'plasticBags'], $opts));
    }

    public function test_dry_run_changes_nothing(): void
    {
        $this->repair()->assertExitCode(0);

        $this->assertSame(1, $this->orphanCount());
        $this->assertSame(0, CategoryObject::where('litter_object_id', $this->legacy->id)->count());
    }

    public function test_apply_creates_pivot_and_attaches_rows(): void
    {
        $this->repair(['--apply' => true])->assertExitCode(0);

        $this->assertSame(0, $this->orphanCount());

        $clo = CategoryObject::where('litter_object_id', $this->legacy->id)
            ->where('category_id', $this->category->id)
            ->firstOrFail();

        $this->assertEquals(
            $clo->id,
            PhotoTag::where('litter_object_id', $this->legacy->id)->value('category_litter_object_id')
        );
    }

    public function test_apply_performs_no_taxonomy_change(): void
    {
        $before = PhotoTag::where('litter_object_id', $this->legacy->id)->firstOrFail();

        $this->repair(['--apply' => true])->assertExitCode(0);

        $after = PhotoTag::find($before->id);

        $this->assertEquals($before->litter_object_id, $after->litter_object_id);
        $this->assertEquals($before->category_id, $after->category_id);
        $this->assertEquals($before->litter_object_type_id, $after->litter_object_type_id);
        $this->assertEquals(7, $after->quantity);
        $this->assertDatabaseHas('litter_objects', ['id' => $this->legacy->id, 'key' => 'plasticBags']);
    }

    public function test_rows_are_never_merged_or_deleted(): void
    {
        PhotoTag::create([
            'photo_id' => $this->photo->id,
            'category_id' => $this->category->id,
            'litter_object_id' => $this->legacy->id,
            'category_litter_object_id' => null,
            'quantity' => 3,
        ]);

        $this->repair(['--apply' => true])->assertExitCode(0);

        $rows = PhotoTag::where('litter_object_id', $this->legacy->id)->get();

        $this->assertCount(2, $rows, 'physically distinct rows must be preserved');
        $this->assertEquals(10, $rows->sum('quantity'));
    }

    /**
     * P1 regression: selection must key off orphaned ROWS, not pivot absence. A run
     * interrupted after the pivot was created must still be discoverable and completable.
     */
    public function test_existing_pivot_with_orphaned_rows_is_still_repairable(): void
    {
        // Simulate an interrupted run: pivot exists, rows still orphaned.
        $clo = CategoryObject::create([
            'category_id' => $this->category->id,
            'litter_object_id' => $this->legacy->id,
        ]);

        $this->assertSame(1, $this->orphanCount());

        $this->repair(['--apply' => true])->assertExitCode(0);

        $this->assertSame(0, $this->orphanCount(), 'rerun must finish an interrupted repair');
        $this->assertEquals(
            $clo->id,
            PhotoTag::where('litter_object_id', $this->legacy->id)->value('category_litter_object_id'),
            'must reuse the existing pivot, not create a duplicate'
        );
        $this->assertSame(1, CategoryObject::where('litter_object_id', $this->legacy->id)->count());
    }

    public function test_partial_repair_is_completed_by_a_rerun(): void
    {
        for ($i = 0; $i < 4; $i++) {
            PhotoTag::create([
                'photo_id' => $this->photo->id,
                'category_id' => $this->category->id,
                'litter_object_id' => $this->legacy->id,
                'category_litter_object_id' => null,
                'quantity' => 1,
            ]);
        }

        // Batch of 2 leaves rows behind on the first pass in a crash scenario; the command
        // loops internally, so assert the end state and that a rerun is a clean no-op.
        $this->repair(['--apply' => true, '--batch' => 2])->assertExitCode(0);
        $this->assertSame(0, $this->orphanCount());

        $this->repair(['--apply' => true])->assertExitCode(0);
        $this->assertSame(0, $this->orphanCount());
    }

    /**
     * Quantity is the hard invariant. xp/total_tags may be recomputed by summary
     * regeneration when the denormalised value was stale, so the meaningful assertion is
     * that the repair is idempotent — a second run must not drift anything further.
     */
    public function test_quantity_is_invariant_and_the_repair_is_idempotent(): void
    {
        $qtyBefore = (int) PhotoTag::where('litter_object_id', $this->legacy->id)->sum('quantity');

        $this->repair(['--apply' => true])->assertExitCode(0);

        $afterFirst = $this->photo->fresh();
        $this->assertSame(
            $qtyBefore,
            (int) PhotoTag::where('litter_object_id', $this->legacy->id)->sum('quantity'),
            'quantity must never change'
        );

        $this->repair(['--apply' => true])->assertExitCode(0);

        $afterSecond = $this->photo->fresh();
        $this->assertEquals($afterFirst->xp, $afterSecond->xp, 'xp must not drift on a rerun');
        $this->assertEquals($afterFirst->total_tags, $afterSecond->total_tags, 'total_tags must not drift on a rerun');
        $this->assertSame(
            $qtyBefore,
            (int) PhotoTag::where('litter_object_id', $this->legacy->id)->sum('quantity')
        );
    }

    public function test_summary_is_regenerated_with_the_new_clo_id(): void
    {
        $this->repair(['--apply' => true])->assertExitCode(0);

        $clo = CategoryObject::where('litter_object_id', $this->legacy->id)->firstOrFail();
        $cloIds = collect($this->photo->fresh()->summary['tags'] ?? [])->pluck('clo_id')->all();

        $this->assertContains($clo->id, $cloIds);
    }

    public function test_it_only_touches_the_named_object(): void
    {
        $other = LitterObject::firstOrCreate(['key' => 'bagsLitter'], ['crowdsourced' => true]);

        PhotoTag::create([
            'photo_id' => $this->photo->id,
            'category_id' => $this->category->id,
            'litter_object_id' => $other->id,
            'category_litter_object_id' => null,
            'quantity' => 5,
        ]);

        $this->repair(['--apply' => true])->assertExitCode(0);

        $this->assertSame(0, $this->orphanCount());
        $this->assertSame(
            1,
            PhotoTag::where('litter_object_id', $other->id)->whereNull('category_litter_object_id')->count(),
            'other objects must be left alone'
        );
    }

    // ── Guard rails ──────────────────────────────────────────────────────────

    public function test_all_with_apply_is_rejected(): void
    {
        $this->artisan('olm:repair-tag-pivot', ['--all' => true, '--apply' => true])->assertExitCode(1);

        $this->assertSame(1, $this->orphanCount(), 'bulk apply must not run');
    }

    public function test_all_previews_without_applying(): void
    {
        $this->artisan('olm:repair-tag-pivot', ['--all' => true])->assertExitCode(0);

        $this->assertSame(1, $this->orphanCount());
    }

    public function test_unknown_object_key_fails(): void
    {
        $this->artisan('olm:repair-tag-pivot', ['--object' => 'no_such_object_xyz'])->assertExitCode(1);
    }

    public function test_object_spanning_multiple_categories_requires_a_category_on_apply(): void
    {
        $marine = Category::where('key', 'marine')->firstOrFail();

        PhotoTag::create([
            'photo_id' => $this->photo->id,
            'category_id' => $marine->id,
            'litter_object_id' => $this->legacy->id,
            'category_litter_object_id' => null,
            'quantity' => 2,
        ]);

        $this->repair(['--apply' => true])->assertExitCode(1);
        $this->assertSame(2, $this->orphanCount(), 'ambiguous apply must be refused');

        // Naming the category makes it unambiguous.
        $this->repair(['--apply' => true, '--category' => 'other'])->assertExitCode(0);

        $this->assertSame(1, $this->orphanCount(), 'only the named category should be repaired');
    }

    public function test_expectations_abort_on_mismatch(): void
    {
        $this->repair(['--apply' => true, '--expect-rows' => 999])->assertExitCode(1);
        $this->assertSame(1, $this->orphanCount());

        $this->repair(['--apply' => true, '--expect-rows' => 1, '--expect-items' => 7])->assertExitCode(0);
        $this->assertSame(0, $this->orphanCount());
    }

    public function test_already_repaired_object_returns_success(): void
    {
        $this->repair(['--apply' => true])->assertExitCode(0);
        $this->repair(['--apply' => true])->assertExitCode(0);
    }

    // ── Tag picker ───────────────────────────────────────────────────────────

    public function test_repaired_legacy_pair_stays_out_of_the_tag_picker(): void
    {
        $this->repair(['--apply' => true])->assertExitCode(0);

        $this->assertSame(1, CategoryObject::where('litter_object_id', $this->legacy->id)->count());

        $keys = collect($this->getJson('/api/tags/all')->json('objects') ?? [])->pluck('key');

        $this->assertNotContains('plasticBags', $keys);
        $this->assertContains('plastic_bag', $keys);
    }

    /**
     * P1 regression: object-level filtering missed canonical objects repaired into a category
     * they do not belong to (e.g. marine/bag). Selectability is defined by TagsConfig pairs.
     */
    public function test_repaired_canonical_pair_in_a_foreign_category_stays_unselectable(): void
    {
        $marine = Category::where('key', 'marine')->firstOrFail();
        $bag = LitterObject::where('key', 'bag')->firstOrFail();

        $this->assertSame(0, (int) $bag->crowdsourced, 'fixture: bag is canonical, not crowdsourced');

        PhotoTag::create([
            'photo_id' => $this->photo->id,
            'category_id' => $marine->id,
            'litter_object_id' => $bag->id,
            'category_litter_object_id' => null,
            'quantity' => 4,
        ]);

        $this->artisan('olm:repair-tag-pivot', [
            '--object' => 'bag', '--category' => 'marine', '--apply' => true,
        ])->assertExitCode(0);

        // The pivot now exists on a canonical object — object-level filtering would expose it.
        $this->assertSame(
            1,
            CategoryObject::where('category_id', $marine->id)->where('litter_object_id', $bag->id)->count()
        );

        $marineObjects = collect($this->getJson('/api/tags')->json('tags') ?? [])
            ->firstWhere('key', 'marine')['litter_objects'] ?? [];

        $this->assertNotContains(
            'bag',
            collect($marineObjects)->pluck('key'),
            'marine/bag is not a TagsConfig pair and must not become taggable'
        );

        $all = $this->getJson('/api/tags/all');

        $cloIds = collect($all->json('category_objects') ?? [])
            ->where('category_id', $marine->id)
            ->pluck('litter_object_id');

        $this->assertNotContains($bag->id, $cloIds, 'repaired pair must not appear in category_objects');

        // The tagging UI builds one searchable entry per obj.categories, so the embedded
        // relation must be filtered too — filtering category_objects alone is not enough.
        $bagObject = collect($all->json('objects') ?? [])->firstWhere('key', 'bag');

        $this->assertNotNull($bagObject, 'bag is canonical in food and must still be offered');
        $this->assertNotContains(
            'marine',
            collect($bagObject['categories'] ?? [])->pluck('key'),
            'repaired marine pair must not leak through the embedded categories list'
        );
        $this->assertContains(
            'food',
            collect($bagObject['categories'] ?? [])->pluck('key'),
            'the canonical food/bag pair must survive filtering'
        );
    }

    /**
     * P1 regression: if every row gets its CLO but summary regeneration never completes, the
     * pair has no orphaned rows left and is invisible to a row-based query. The persisted
     * state must itself be a target, or "rerun to retry" is a false promise.
     */
    public function test_pending_summary_state_is_discovered_after_all_rows_are_repaired(): void
    {
        $clo = CategoryObject::create([
            'category_id' => $this->category->id,
            'litter_object_id' => $this->legacy->id,
        ]);

        // Simulate a crash between the row update and summary regeneration.
        PhotoTag::where('litter_object_id', $this->legacy->id)
            ->update(['category_litter_object_id' => $clo->id]);

        $this->photo->update(['summary' => null]);

        Storage::disk('local')->put(
            "repair-tag-pivot/{$this->category->id}-{$this->legacy->id}.json",
            json_encode([
                'version' => 1,
                'category_id' => $this->category->id,
                'category_key' => 'other',
                'litter_object_id' => $this->legacy->id,
                'object_key' => 'plasticBags',
                'baseline_rows' => 1,
                'baseline_items' => 7,
                'photo_ids' => [$this->photo->id],
            ])
        );

        $this->assertSame(0, $this->orphanCount(), 'fixture: no orphaned rows remain');

        $this->repair(['--apply' => true])->assertExitCode(0);

        $this->assertNotNull($this->photo->fresh()->summary, 'the outstanding summary must be regenerated');
        $this->assertFalse(
            Storage::disk('local')->exists("repair-tag-pivot/{$this->category->id}-{$this->legacy->id}.json"),
            'state must be cleared once the queue is drained'
        );
    }

    public function test_corrupt_recovery_state_aborts_rather_than_being_ignored(): void
    {
        Storage::disk('local')->put(
            "repair-tag-pivot/{$this->category->id}-{$this->legacy->id}.json",
            '{ not valid json'
        );

        $this->expectException(\RuntimeException::class);

        $this->artisan('olm:repair-tag-pivot', ['--object' => 'plasticBags'])->run();
    }

    public function test_stale_recovery_state_aborts(): void
    {
        Storage::disk('local')->put(
            "repair-tag-pivot/{$this->category->id}-{$this->legacy->id}.json",
            json_encode([
                'version' => 1,
                'category_id' => $this->category->id,
                'category_key' => 'other',
                'litter_object_id' => $this->legacy->id,
                'object_key' => 'a_different_object',   // no longer matches the database
                'baseline_rows' => 1,
                'baseline_items' => 7,
                'photo_ids' => [$this->photo->id],
            ])
        );

        $this->expectException(\RuntimeException::class);

        $this->artisan('olm:repair-tag-pivot', ['--object' => 'plasticBags'])->run();
    }

    public function test_unknown_category_key_fails(): void
    {
        $this->artisan('olm:repair-tag-pivot', [
            '--object' => 'plasticBags', '--category' => 'no_such_category',
        ])->assertExitCode(1);

        $this->assertSame(1, $this->orphanCount(), 'a typo must not silently no-op');
    }

    public function test_repair_does_not_change_the_selectable_pair_count(): void
    {
        $before = count(CategoryObject::selectableIds());

        $this->repair(['--apply' => true])->assertExitCode(0);

        $this->assertSame(
            $before,
            count(CategoryObject::selectableIds()),
            'repair must never widen what users can tag'
        );
    }
}
