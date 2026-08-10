<?php

namespace Tests\Feature\Migration;

use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CategoryObject;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Location\Country;
use App\Models\Photo;
use App\Models\Users\User;
use App\Services\Redis\RedisKeys;
use Database\Seeders\Tags\GenerateTagsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Entry 1 retires the canonical `plastic_bag` in favour of the migration-minted `plasticBags`
 * (decision D-4), because the latter holds 10,051 of the 10,304 production rows.
 *
 * That inverts every boundary the pre-D-4 command asserted: the RETIRED side is the one with a
 * pivot and quick tags, and the SURVIVING side is the one that needs a pivot created.
 */
class MigrateTagTest extends TestCase
{
    private const QUEUE = 'storage/framework/testing/tag-retirements.csv';

    private Category $category;
    private LitterObject $retired;
    private LitterObject $desired;
    private CategoryObject $retiredClo;
    private Photo $photo;
    private PhotoTag $tag;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GenerateTagsSeeder::class);
        Storage::fake('local');

        $this->category = Category::where('key', 'other')->firstOrFail();

        // The key being retired. Step B.4 took it out of TagsConfig, so the seeder no longer
        // creates it — but the production row still exists and still holds its pivot, which is
        // exactly the state a run starts from. Build that here rather than lean on the config.
        $this->retired = LitterObject::firstOrCreate(['key' => 'plastic_bag'], ['crowdsourced' => false]);
        $this->retiredClo = CategoryObject::firstOrCreate([
            'category_id' => $this->category->id,
            'litter_object_id' => $this->retired->id,
        ]);

        // The surviving key is now IN TagsConfig, so the seeder hands it a pivot. Production has
        // none yet — the run creates it (A.2) — so drop the seeded one to restore that premise.
        $this->desired = LitterObject::firstOrCreate(['key' => 'plasticBags'], ['crowdsourced' => true]);
        CategoryObject::where('litter_object_id', $this->desired->id)->delete();

        $user = User::factory()->create();
        $this->photo = Photo::factory()->create(['verified' => 2, 'user_id' => $user->id]);

        $this->tag = PhotoTag::create([
            'photo_id' => $this->photo->id,
            'category_id' => $this->category->id,
            'litter_object_id' => $this->retired->id,
            'category_litter_object_id' => $this->retiredClo->id,
            'quantity' => 7,
        ]);

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
            'entry_id' => 'other--plastic_bag',
            'status' => $status,
            'class' => 'twin',
            'category_key' => 'other',
            'category_id' => $this->category->id,
            'retired_key' => 'plastic_bag',
            'retired_id' => $this->retired->id,
            'desired_key' => 'plasticBags',
            'desired_id' => $this->desired->id,
            'desired_clo_id' => '',
            'retired_clo_id' => $this->retiredClo->id,
            'retired_rows' => 1,
            'retired_items' => 7,
            'retired_photos' => 1,
            'desired_rows' => 0,
            'desired_items' => 0,
            'desired_photos' => 0,
            'xp_retired_per_item' => 1,
            'xp_desired_per_item' => 1,
            'xp_equivalent' => 'yes',
            'quick_tags_on_retired_clo' => 0,
            'approver' => '',
            'approved_at' => '',
            'verification_evidence' => '',
            'notes' => '',
        ], $overrides);

        @mkdir(dirname(base_path(self::QUEUE)), 0777, true);
        $fh = fopen(base_path(self::QUEUE), 'w');
        fputcsv($fh, array_keys($row));
        fputcsv($fh, array_values($row));
        fclose($fh);
    }

    /**
     * Read the single entry back out of the queue file.
     *
     * @return array<string, string>
     */
    private function queueRow(): array
    {
        $fh = fopen(base_path(self::QUEUE), 'r');
        $header = fgetcsv($fh);
        $row = fgetcsv($fh);
        fclose($fh);

        return array_combine($header, $row);
    }

    private function migrate(array $opts = []): \Illuminate\Testing\PendingCommand
    {
        return $this->artisan('olm:migrate-tag', array_merge([
            '--entry' => 'other--plastic_bag',
            '--queue' => self::QUEUE,
        ], $opts));
    }

    private function applyReady(): void
    {
        $this->writeQueue('DRY_RUN_VERIFIED');
    }

    /** Bring the entry to the apply gate and run a successful apply. */
    private function applyOk(): void
    {
        $this->applyReady();
        $this->migrate(['--apply' => true])->assertExitCode(0);
    }

    private function insertQuickTag(int $cloId): void
    {
        DB::table('user_quick_tags')->insert([
            'user_id' => User::factory()->create()->id,
            'clo_id' => $cloId,
            'quantity' => 1,
            'materials' => '[]',
            'brands' => '[]',
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ── Gating ───────────────────────────────────────────────────────────────

    public function test_apply_is_blocked_until_dry_run_verified(): void
    {
        $this->migrate(['--apply' => true])->assertExitCode(1);

        $this->assertDatabaseHas('photo_tags', [
            'id' => $this->tag->id,
            'litter_object_id' => $this->retired->id,
        ]);
    }

    public function test_apply_is_still_blocked_at_code_updated(): void
    {
        $this->writeQueue('CODE_UPDATED');

        $this->migrate(['--apply' => true])->assertExitCode(1);

        $this->assertDatabaseHas('photo_tags', [
            'id' => $this->tag->id,
            'litter_object_id' => $this->retired->id,
        ]);
    }

    public function test_status_transitions_must_be_sequential(): void
    {
        $this->migrate(['--advance' => 'LOCAL_APPLIED', '--by' => 'sean'])->assertExitCode(1);
    }

    public function test_advance_requires_an_author(): void
    {
        $this->migrate(['--advance' => 'MAPPING_APPROVED'])->assertExitCode(1);
    }

    public function test_verified_transitions_require_evidence(): void
    {
        $this->writeQueue('CODE_UPDATED');

        $this->migrate(['--advance' => 'DRY_RUN_VERIFIED', '--by' => 'sean'])->assertExitCode(1);
    }

    public function test_advancing_preserves_the_original_approval_and_records_every_transition(): void
    {
        $this->writeQueue('MAPPING_APPROVED', [
            'approver' => 'product-owner',
            'approved_at' => '2026-08-08',
            'verification_evidence' => 'REHEARSED 3x on olm_postmig_3',
        ]);

        $this->migrate(['--advance' => 'CODE_UPDATED', '--by' => 'sean'])->assertExitCode(0);
        $this->migrate([
            '--advance' => 'DRY_RUN_VERIFIED',
            '--by' => 'sean',
            '--evidence' => 'dry run counts matched',
        ])->assertExitCode(0);

        $row = $this->queueRow();

        $this->assertSame('DRY_RUN_VERIFIED', $row['status']);
        $this->assertSame('product-owner', $row['approver'], 'The mapping approval must survive later rungs.');
        $this->assertSame('2026-08-08', $row['approved_at']);

        $this->assertStringContainsString('REHEARSED 3x on olm_postmig_3', $row['verification_evidence']);
        $this->assertStringContainsString('CODE_UPDATED by sean', $row['verification_evidence']);
        $this->assertStringContainsString('DRY_RUN_VERIFIED by sean', $row['verification_evidence']);
        $this->assertStringContainsString('dry run counts matched', $row['verification_evidence']);
    }

    public function test_unknown_entry_fails(): void
    {
        $this->artisan('olm:migrate-tag', ['--entry' => 'nope', '--queue' => self::QUEUE])
            ->assertExitCode(1);
    }

    public function test_expectation_mismatch_aborts(): void
    {
        $this->writeQueue('DRY_RUN_VERIFIED', ['retired_items' => 999]);

        $this->migrate(['--apply' => true])->assertExitCode(1);

        $this->assertDatabaseHas('photo_tags', [
            'id' => $this->tag->id,
            'litter_object_id' => $this->retired->id,
        ]);
    }

    public function test_there_is_no_bulk_apply(): void
    {
        $signature = (new \ReflectionClass(\App\Console\Commands\tmp\v5\PostMigAug2026\MigrateTag::class))
            ->getDefaultProperties()['signature'];

        $this->assertStringNotContainsString('--all', $signature);
        $this->assertStringNotContainsString('bulk', $signature);
    }

    // ── Unimplemented scopes are refused, not half-applied ──────────────────

    public function test_type_expansions_are_refused(): void
    {
        $type = DB::table('litter_object_types')->insertGetId([
            'key' => 'testtype', 'name' => 'Test Type', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->tag->update(['litter_object_type_id' => $type]);

        $this->applyReady();

        $this->migrate(['--apply' => true])->assertExitCode(1);
        $this->assertDatabaseHas('photo_tags', ['id' => $this->tag->id, 'litter_object_id' => $this->retired->id]);
    }

    public function test_object_tagged_across_several_categories_is_refused(): void
    {
        $other = Category::where('key', '!=', 'other')->firstOrFail();

        PhotoTag::create([
            'photo_id' => $this->photo->id,
            'category_id' => $other->id,
            'litter_object_id' => $this->retired->id,
            'quantity' => 1,
        ]);

        $this->applyReady();

        $this->migrate(['--apply' => true])->assertExitCode(1);
    }

    /**
     * XP is weighted by object key. bags_litter is worth 10 and bagsLitter 1, so retiring one
     * for the other silently moves user scores unless the owner has accepted it.
     */
    public function test_xp_weight_change_is_refused(): void
    {
        $bagsLitter = LitterObject::firstOrCreate(['key' => 'bags_litter']);
        $camel = LitterObject::firstOrCreate(['key' => 'bagsLitter'], ['crowdsourced' => true]);

        CategoryObject::firstOrCreate([
            'category_id' => $this->category->id,
            'litter_object_id' => $bagsLitter->id,
        ]);

        $this->tag->update(['litter_object_id' => $bagsLitter->id, 'category_litter_object_id' => null]);

        // 'accepted' was the old override. It no longer exists — the refusal is unconditional.
        $this->writeQueue('DRY_RUN_VERIFIED', [
            'retired_key' => 'bags_litter', 'retired_id' => $bagsLitter->id,
            'desired_key' => 'bagsLitter', 'desired_id' => $camel->id,
            'xp_equivalent' => 'accepted',
        ]);

        $this->migrate(['--apply' => true])->assertExitCode(1);

        $this->assertDatabaseHas('photo_tags', [
            'id' => $this->tag->id,
            'litter_object_id' => $bagsLitter->id,
        ]);
    }

    // ── Apply ────────────────────────────────────────────────────────────────

    public function test_apply_repoints_the_row_and_preserves_everything_else(): void
    {
        $this->applyReady();

        $this->migrate(['--apply' => true])->assertExitCode(0);

        $row = DB::table('photo_tags')->where('id', $this->tag->id)->first();

        $this->assertSame($this->desired->id, (int) $row->litter_object_id);
        $this->assertSame($this->category->id, (int) $row->category_id);
        $this->assertSame(7, (int) $row->quantity);
        $this->assertNull($row->litter_object_type_id);
    }

    public function test_apply_creates_the_surviving_pivot(): void
    {
        $this->assertDatabaseMissing('category_litter_object', [
            'category_id' => $this->category->id,
            'litter_object_id' => $this->desired->id,
        ]);

        $this->applyOk();

        $this->assertDatabaseHas('category_litter_object', [
            'category_id' => $this->category->id,
            'litter_object_id' => $this->desired->id,
        ]);
    }

    public function test_picker_is_closed_before_data_moves(): void
    {
        $this->applyOk();

        $this->retired->refresh();

        $this->assertNotNull($this->retired->retired_at);
        $this->assertSame($this->desired->id, (int) $this->retired->merged_into_id);
    }

    public function test_retired_object_is_excluded_from_the_tag_picker(): void
    {
        $this->applyOk();

        $response = $this->getJson('/api/tags/all')->assertOk();
        $keys = collect($response->json('objects') ?? $response->json('litterObjects') ?? [])->pluck('key');

        $this->assertNotContains('plastic_bag', $keys);
        $this->assertContains('plasticBags', $keys);
    }

    public function test_retired_object_can_no_longer_be_tagged(): void
    {
        $this->retired->update(['retired_at' => now(), 'merged_into_id' => $this->desired->id]);

        $user = User::factory()->create();
        $photo = Photo::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->postJson('/api/v3/tags', [
                'photo_id' => $photo->id,
                'tags' => [[
                    'category_litter_object_id' => $this->retiredClo->id,
                    'quantity' => 1,
                ]],
            ])
            ->assertStatus(422);
    }

    public function test_retired_pivot_is_removed_once_drained(): void
    {
        $this->applyOk();

        $this->assertDatabaseMissing('category_litter_object', ['id' => $this->retiredClo->id]);
    }

    public function test_summary_reflects_the_surviving_object(): void
    {
        $this->applyOk();

        $summary = $this->photo->fresh()->summary;
        $summary = is_string($summary) ? json_decode($summary, true) : $summary;
        $objectKeys = array_map('intval', array_keys($summary['keys']['objects'] ?? []));

        $this->assertContains($this->desired->id, $objectKeys);
        $this->assertNotContains($this->retired->id, $objectKeys);
    }

    public function test_xp_does_not_move(): void
    {
        $before = (int) $this->photo->fresh()->xp;

        $this->applyOk();

        $this->assertSame($before, (int) $this->photo->fresh()->xp);
    }

    public function test_colliding_rows_are_never_merged(): void
    {
        $existing = PhotoTag::create([
            'photo_id' => $this->photo->id,
            'category_id' => $this->category->id,
            'litter_object_id' => $this->desired->id,
            'quantity' => 3,
        ]);

        // Settle the photo with BOTH tags present, so the XP baseline the command captures
        // reflects the collision rather than being changed by it.
        $this->photo->generateSummary();
        $this->photo->refresh();

        $this->applyOk();

        $this->assertSame(2, DB::table('photo_tags')
            ->where('photo_id', $this->photo->id)
            ->where('litter_object_id', $this->desired->id)
            ->count());

        $this->assertSame(3, (int) DB::table('photo_tags')->where('id', $existing->id)->value('quantity'));
        $this->assertSame(7, (int) DB::table('photo_tags')->where('id', $this->tag->id)->value('quantity'));
    }

    // ── Quick tags ───────────────────────────────────────────────────────────

    /**
     * Restored. It was deleted on the reasoning that a pivotless source cannot hold a stored
     * CLO reference — true in the pre-D-4 direction, false in this one. 128 production rows
     * sit on the retired pivot.
     */
    public function test_quick_tags_are_repointed_off_the_retired_pivot(): void
    {
        $this->insertQuickTag($this->retiredClo->id);

        $this->applyOk();

        $newClo = DB::table('category_litter_object')
            ->where('category_id', $this->category->id)
            ->where('litter_object_id', $this->desired->id)
            ->value('id');

        $this->assertSame(0, DB::table('user_quick_tags')->where('clo_id', $this->retiredClo->id)->count());
        $this->assertSame(1, DB::table('user_quick_tags')->where('clo_id', $newClo)->count());
    }

    public function test_quick_tags_in_other_categories_are_untouched(): void
    {
        $otherClo = CategoryObject::where('litter_object_id', '!=', $this->retired->id)->firstOrFail();

        $this->insertQuickTag($otherClo->id);

        $this->applyOk();

        $this->assertSame(1, DB::table('user_quick_tags')->where('clo_id', $otherClo->id)->count());
    }

    // ── Verify ───────────────────────────────────────────────────────────────

    public function test_verify_passes_after_a_clean_apply(): void
    {
        $this->applyOk();

        $this->migrate(['--verify' => true])->assertExitCode(0);
    }

    public function test_verify_fails_when_a_row_remains_on_the_retired_object(): void
    {
        $this->applyOk();

        PhotoTag::create([
            'photo_id' => $this->photo->id,
            'category_id' => $this->category->id,
            'litter_object_id' => $this->retired->id,
            'quantity' => 1,
        ]);

        $this->migrate(['--verify' => true])->assertExitCode(1);
    }

    /**
     * Dropping the retired pivot cascades to user_quick_tags, so a run that forgot to repoint
     * would silently destroy saved presets rather than leave them behind. Verification has to
     * assert survival on the surviving pivot, not absence on the retired one.
     */
    public function test_verify_fails_when_quick_tags_did_not_survive_the_repoint(): void
    {
        $this->insertQuickTag($this->retiredClo->id);

        $this->applyOk();

        DB::table('user_quick_tags')->delete();
        $this->writeQueue('LOCAL_APPLIED', ['quick_tags_on_retired_clo' => 1]);

        $this->migrate(['--verify' => true])->assertExitCode(1);
    }

    /**
     * Reconciliation must actually READ the per-country and per-user hashes, not just the global
     * one. Caught on the rehearsal database: a grouped `pluck()` re-selects only the columns it
     * is handed, discarding the selectRaw aliases, and returned 0 where the real figure was 951.
     * Every test passed through that bug because no fixture photo carried a country.
     */
    public function test_verify_reconciles_country_and_user_scopes(): void
    {
        $country = Country::factory()->create();
        $this->photo->update(['country_id' => $country->id, 'processed_at' => now()]);

        $this->applyOk();

        $this->migrate(['--verify' => true])->assertExitCode(0);

        // Corrupt ONLY the country hash. A global-scope check would not notice this.
        Redis::hset(
            RedisKeys::objects(RedisKeys::country($country->id)),
            (string) $this->desired->id,
            9999
        );

        $this->migrate(['--verify' => true])->assertExitCode(1);
    }

    public function test_complete_is_refused_when_verification_fails(): void
    {
        $this->writeQueue('PRODUCTION_VERIFIED');

        $this->migrate([
            '--advance' => 'COMPLETE',
            '--by' => 'sean',
        ])->assertExitCode(1);
    }

    // ── Resumability ─────────────────────────────────────────────────────────

    public function test_rerun_after_completion_is_a_clean_no_op(): void
    {
        $this->applyOk();

        // The retired object is drained, so the expectation check no longer matches.
        $this->migrate(['--apply' => true])->assertExitCode(1);

        $this->assertSame(1, DB::table('photo_tags')
            ->where('id', $this->tag->id)
            ->where('litter_object_id', $this->desired->id)
            ->count());
    }

    public function test_editing_the_entry_blocks_a_resume(): void
    {
        $this->applyReady();

        Storage::disk('local')->put('migrate-tag/other--plastic_bag.json', json_encode([
            'version' => 3,
            'entry_id' => 'other--plastic_bag',
            'mapping_fingerprint' => 'stale-fingerprint',
            'baseline_rows' => 1,
            'baseline_items' => 7,
            'baseline_xp' => 0,
            'retired_object_id' => $this->retired->id,
            'category_id' => $this->category->id,
            'desired_clo_id' => null,
            'tag_ids' => [$this->tag->id],
            'all_photo_ids' => [$this->photo->id],
            'pending_photo_ids' => [$this->photo->id],
        ]));

        $this->migrate(['--apply' => true])->assertExitCode(1);

        $this->assertDatabaseHas('photo_tags', [
            'id' => $this->tag->id,
            'litter_object_id' => $this->retired->id,
        ]);
    }
}
