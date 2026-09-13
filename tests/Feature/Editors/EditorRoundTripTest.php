<?php

namespace Tests\Feature\Editors;

use App\Events\SchoolDataApproved;
use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CategoryObject;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\LitterObjectType;
use App\Models\Litter\Tags\Materials;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Photo;
use App\Models\Users\User;
use Closure;
use Database\Seeders\Tags\GenerateTagsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use Symfony\Component\Process\Process;
use Tests\Helpers\CreatesSchoolTeamTrait;
use Tests\TestCase;

/**
 * - Real API response → the shared editor adapter (run in Node) → one edit → real save endpoint → stored rows.
 * - Web and admin editors work on an ordinary owner's photo; the facilitator queue and the team modal
 *   share the team endpoints and the same adapter, so one team round trip covers both.
 */
class EditorRoundTripTest extends TestCase
{
    use CreatesSchoolTeamTrait;

    private User $owner;
    private User $admin;
    private int $clo;
    private int $typeId;
    private int $brandId;
    private int $materialId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCreatesSchoolTeam();
        $this->clo = CategoryObject::whereHas('litterObject', fn ($q) => $q->where('key', 'cigarette_butt'))->firstOrFail()->id;
        $this->typeId = LitterObjectType::firstOrCreate(['key' => 'audit_type'], ['name' => 'Audit type'])->id;
        DB::table('category_object_types')->insert(['category_litter_object_id' => $this->clo, 'litter_object_type_id' => $this->typeId]);
        $this->brandId = BrandList::firstOrCreate(['key' => 'audit-brand'])->id;
        $this->materialId = Materials::firstOrCreate(['key' => 'audit-material'])->id;
        $this->owner = User::factory()->create(['verification_required' => true]);
        $this->admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin->assignRole('admin');
    }

    // ---------- fixtures ----------

    private function fullTag(?bool $pickedUp = true): array
    {
        return ['category_litter_object_id' => $this->clo, 'litter_object_type_id' => $this->typeId, 'quantity' => 4,
            'picked_up' => $pickedUp, 'brands' => [['id' => $this->brandId, 'quantity' => 3]],
            'materials' => [$this->materialId], 'custom_tags' => ['audit-note']];
    }

    private function standaloneTags(): array
    {
        return [
            ['brand_only' => true, 'brand' => ['id' => $this->brandId], 'quantity' => 2, 'picked_up' => false],
            ['material_only' => true, 'material' => ['id' => $this->materialId], 'quantity' => 3, 'picked_up' => true],
            ['custom' => true, 'key' => 'audit-standalone', 'quantity' => 1, 'picked_up' => null],
        ];
    }

    /** Photo owned by a plain untrusted user, tagged through POST /api/v3/tags (web + admin cases). */
    private function ownerPhoto(array $tags): Photo
    {
        $photo = Photo::factory()->create(['user_id' => $this->owner->id, 'is_public' => true]);
        $this->actingAs($this->owner)->postJson('/api/v3/tags', ['photo_id' => $photo->id, 'tags' => $tags])->assertOk();

        return $photo->fresh();
    }

    /** School photo tagged by the student (team cases). */
    private function schoolPhoto(array $tags): Photo
    {
        $photo = Photo::factory()->create(['user_id' => $this->student->id, 'team_id' => $this->schoolTeam->id]);
        $this->actingAs($this->student)->postJson('/api/v3/tags', ['photo_id' => $photo->id, 'tags' => $tags])->assertOk();

        return $photo->fresh();
    }

    /** Add a row on a category/object that has no CLO (like production plasticBags) and refresh the summary. */
    private function addUnresolvedRow(Photo $photo): void
    {
        $other = Category::firstOrCreate(['key' => 'other']);
        $shadow = LitterObject::firstOrCreate(['key' => 'plasticBags']);
        PhotoTag::create(['photo_id' => $photo->id, 'category_id' => $other->id, 'litter_object_id' => $shadow->id, 'quantity' => 2, 'picked_up' => false]);
        $photo->generateSummary();
    }

    /** Stored rows in id order: category/object, type, quantity, picked_up (raw DB value) and sorted extras. */
    private function shape(Photo $photo): array
    {
        return $photo->photoTags()->orderBy('id')->get()->map(function (PhotoTag $t) {
            $extras = $t->extraTags()->get()->map(fn ($e) => [$e->tag_type, (int) $e->tag_type_id, (int) $e->quantity])->sort()->values()->all();

            return ['cat' => $t->category_id, 'obj' => $t->litter_object_id, 'type' => $t->litter_object_type_id,
                'qty' => (int) $t->quantity, 'picked_up' => $t->picked_up, 'extras' => $extras];
        })->all();
    }

    /** Run the shared adapter in Node on either an API photo or prepared cards. */
    private function runBridge(array $input): array
    {
        $process = new Process([getenv('NODE_BINARY') ?: 'node', base_path('tests/Feature/Editors/editorBridge.mjs')]);
        $process->setInput(json_encode($input));
        $process->mustRun();

        return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
    }

    /** Load an API photo into cards, apply one quantity edit to the first card, and build the payload. */
    private function bridge(array $photo, ?int $newQuantity = 5): array
    {
        return $this->runBridge([
            'photo' => $photo,
            'cloMap' => CategoryObject::all()->mapWithKeys(fn ($c) => [$c->category_id . ':' . $c->litter_object_id => $c->id])->all(),
            'types' => LitterObjectType::all(['id', 'key'])->toArray(),
            'edit' => $newQuantity ? ['quantity' => $newQuantity] : null,
        ]);
    }

    private function expectedAfterEdit(array $before, int $newQuantity = 5): array
    {
        $before[0]['qty'] = $newQuantity;
        if ($before[0]['obj'] === null && count($before[0]['extras']) === 1 && $before[0]['extras'][0][0] === 'brand') {
            $before[0]['extras'][0][2] = $newQuantity;
        }

        return $before;
    }

    // ---------- per-editor API loads and round trips ----------

    private function webApi(Photo $photo): array
    {
        $api = $this->actingAs($this->owner)->getJson("/api/v3/user/photos?id={$photo->id}&id_operator==&per_page=1")->assertOk()->json('photos.0');
        $this->assertSame($photo->id, $api['id']);

        return $api;
    }

    private function adminApi(Photo $photo): array
    {
        $api = $this->actingAs($this->admin)->getJson("/api/admin/photos?photo_id={$photo->id}")->assertOk()->json('photos.data.0');
        $this->assertSame($photo->id, $api['id']);

        return $api;
    }

    private function teamApi(Photo $photo): array
    {
        $list = $this->actingAs($this->teacher)->getJson("/api/teams/photos?team_id={$this->schoolTeam->id}&status=all&per_page=100")->assertOk()->json('photos.data');
        foreach ($list as $p) {
            if ($p['id'] === $photo->id) {
                return $p;
            }
        }
        $this->fail('photo not in team list');
    }

    /** AddTags.vue: GET /api/v3/user/photos → PUT /api/v3/tags */
    private function webRoundTrip(Photo $photo): array
    {
        $r = $this->bridge($this->webApi($photo));
        $this->assertFalse($r['unresolved'], 'web editor blocks submit on unresolved tag');
        $this->actingAs($this->owner)->putJson('/api/v3/tags', ['photo_id' => $photo->id, 'tags' => $r['payload']])->assertOk();

        return $r;
    }

    /** AdminQueue.vue: GET /api/admin/photos → POST /api/admin/contentsupdatedelete */
    private function adminRoundTrip(Photo $photo): array
    {
        $r = $this->bridge($this->adminApi($photo));
        $this->actingAs($this->admin)->postJson('/api/admin/contentsupdatedelete', ['photoId' => $photo->id, 'tags' => $r['payload']])->assertOk();

        return $r;
    }

    /** FacilitatorQueue.vue and TeamPhotoEdit.vue: GET /api/teams/photos → PATCH /api/teams/photos/{id}/tags */
    private function teamRoundTrip(Photo $photo): array
    {
        $r = $this->bridge($this->teamApi($photo));
        $this->actingAs($this->teacher)->patchJson("/api/teams/photos/{$photo->id}/tags", ['tags' => $r['payload']])->assertOk();

        return $r;
    }

    /** One fresh photo per editor, tagged with $tags, round-tripped, then handed to $assert(photo, before, result, editor). */
    private function roundTripEachEditor(array $tags, Closure $assert): void
    {
        foreach (['web', 'admin', 'team'] as $editor) {
            $photo = $editor === 'team' ? $this->schoolPhoto($tags) : $this->ownerPhoto($tags);
            $before = $this->shape($photo);
            $result = $this->{$editor . 'RoundTrip'}($photo);
            $assert($photo, $before, $result, $editor);
        }
    }

    // ---------- WEB ----------

    public function test_web_full_object_tag_survives_an_edit(): void
    {
        $photo = $this->ownerPhoto([$this->fullTag(true)]);
        $before = $this->shape($photo);
        $this->webRoundTrip($photo);
        $this->assertSame($this->expectedAfterEdit($before), $this->shape($photo));
    }

    public function test_web_unknown_picked_up_stays_unknown(): void
    {
        $photo = $this->ownerPhoto([$this->fullTag(null)]);
        $this->assertNull($photo->photoTags()->sole()->picked_up);
        $this->webRoundTrip($photo);
        $this->assertNull($photo->photoTags()->sole()->picked_up, 'null collection status must not become true/false');
    }

    public function test_web_false_picked_up_stays_false(): void
    {
        $photo = $this->ownerPhoto([$this->fullTag(false)]);
        $this->webRoundTrip($photo);
        $this->assertSame(0, (int) $photo->photoTags()->sole()->picked_up);
    }

    public function test_web_standalone_brand_material_custom_survive(): void
    {
        $photo = $this->ownerPhoto($this->standaloneTags());
        $before = $this->shape($photo);
        $this->assertCount(3, $before);
        $this->webRoundTrip($photo);
        $this->assertSame($this->expectedAfterEdit($before), $this->shape($photo));
    }

    public function test_web_mixed_standalone_extras_do_not_invent_a_custom_tag(): void
    {
        $photo = $this->ownerPhoto([['brand_only' => true, 'brand' => ['id' => $this->brandId], 'materials' => [$this->materialId], 'quantity' => 2, 'picked_up' => false]]);
        $this->assertSame(0, $photo->photoTags()->sole()->extraTags()->where('tag_type', 'custom_tag')->count());
        $r = $this->webRoundTrip($photo);
        $this->assertSame(0, $photo->photoTags()->sole()->extraTags()->where('tag_type', 'custom_tag')->count(), 'payload was ' . json_encode($r['payload']));
    }

    public function test_web_duplicate_observations_survive(): void
    {
        $photo = $this->ownerPhoto([$this->fullTag(true), ['category_litter_object_id' => $this->clo, 'quantity' => 1, 'picked_up' => false]]);
        $before = $this->shape($photo);
        $this->assertCount(2, $before);
        $this->webRoundTrip($photo);
        $this->assertSame($this->expectedAfterEdit($before), $this->shape($photo));
    }

    public function test_web_rejects_undeclared_category_without_losing_data(): void
    {
        $photo = $this->ownerPhoto([$this->fullTag(true)]);
        $this->addUnresolvedRow($photo);
        $before = $this->shape($photo);
        $r = $this->bridge($this->webApi($photo));
        $this->actingAs($this->owner)->putJson('/api/v3/tags', ['photo_id' => $photo->id, 'tags' => $r['payload']])->assertUnprocessable();
        $this->assertSame($before, $this->shape($photo));
    }

    public function test_web_blocks_submit_for_an_object_without_a_category(): void
    {
        $api = $this->webApi($this->ownerPhoto([$this->fullTag(true)]));
        unset($api['new_tags'][0]['category_litter_object_id'], $api['new_tags'][0]['category']);
        $this->assertTrue($this->bridge($api)['unresolved']);
    }

    // ---------- ADMIN ----------

    public function test_admin_full_object_tag_survives_an_edit(): void
    {
        $photo = $this->ownerPhoto([$this->fullTag(true)]);
        $before = $this->shape($photo);
        $this->adminRoundTrip($photo);
        $this->assertSame($this->expectedAfterEdit($before), $this->shape($photo));
    }

    public function test_admin_known_false_picked_up_stays_known(): void
    {
        $photo = $this->ownerPhoto([$this->fullTag(false)]);
        $this->adminRoundTrip($photo);
        $this->assertSame(0, (int) $photo->photoTags()->sole()->picked_up);
        $this->assertNotNull($photo->photoTags()->sole()->picked_up);
    }

    public function test_admin_known_true_picked_up_stays_known(): void
    {
        $photo = $this->ownerPhoto([$this->fullTag(true)]);
        $this->adminRoundTrip($photo);
        $this->assertSame(1, (int) $photo->photoTags()->sole()->picked_up);
        $this->assertNotNull($photo->photoTags()->sole()->picked_up);
    }

    public function test_admin_unknown_picked_up_stays_unknown(): void
    {
        $photo = $this->ownerPhoto([$this->fullTag(null)]);
        $this->adminRoundTrip($photo);
        $this->assertNull($photo->photoTags()->sole()->picked_up);
    }

    public function test_admin_standalone_tags_can_be_reloaded_and_saved(): void
    {
        $photo = $this->ownerPhoto($this->standaloneTags());
        $before = $this->shape($photo);
        $this->adminRoundTrip($photo);
        $this->assertSame($this->expectedAfterEdit($before), $this->shape($photo));
    }

    public function test_admin_rejects_a_photo_with_an_undeclared_row_and_keeps_all_rows(): void
    {
        $photo = $this->ownerPhoto([$this->fullTag(true)]);
        $this->addUnresolvedRow($photo);
        $before = $this->shape($photo);
        $r = $this->bridge($this->adminApi($photo));
        $this->actingAs($this->admin)->postJson('/api/admin/contentsupdatedelete', ['photoId' => $photo->id, 'tags' => $r['payload']])->assertUnprocessable();
        $this->assertSame($before, $this->shape($photo));
    }

    // ---------- TEAM (facilitator queue and team modal) ----------

    public function test_facilitator_full_object_tag_survives_an_edit(): void
    {
        $photo = $this->schoolPhoto([$this->fullTag(true)]);
        $before = $this->shape($photo);
        $this->assertCount(3, $before[0]['extras']);
        $this->teamRoundTrip($photo);
        $this->assertSame($this->expectedAfterEdit($before), $this->shape($photo));
    }

    public function test_facilitator_known_picked_up_stays_known(): void
    {
        $photo = $this->schoolPhoto([$this->fullTag(true)]);
        $this->teamRoundTrip($photo);
        $this->assertNotNull($photo->photoTags()->sole()->picked_up);
    }

    public function test_facilitator_unknown_picked_up_stays_unknown(): void
    {
        $photo = $this->schoolPhoto([$this->fullTag(null)]);
        $this->teamRoundTrip($photo);
        $this->assertNull($photo->photoTags()->sole()->picked_up);
    }

    public function test_facilitator_standalone_tags_can_be_reloaded_and_saved(): void
    {
        $photo = $this->schoolPhoto($this->standaloneTags());
        $before = $this->shape($photo);
        $this->teamRoundTrip($photo);
        $this->assertSame($this->expectedAfterEdit($before), $this->shape($photo));
    }

    public function test_facilitator_rejects_a_photo_with_an_undeclared_row_and_keeps_all_rows(): void
    {
        $photo = $this->schoolPhoto([$this->fullTag(true)]);
        $this->addUnresolvedRow($photo);
        $before = $this->shape($photo);
        $r = $this->bridge($this->teamApi($photo));
        $this->actingAs($this->teacher)->patchJson("/api/teams/photos/{$photo->id}/tags", ['tags' => $r['payload']])->assertUnprocessable();
        $this->assertSame($before, $this->shape($photo));
        $this->assertFalse((bool) $photo->fresh()->is_public, 'school photo stays private after a rejected save');
    }

    public function test_team_modal_unknown_picked_up_stays_unknown(): void
    {
        $photo = $this->schoolPhoto([['category_litter_object_id' => $this->clo, 'quantity' => 2, 'picked_up' => null]]);
        $this->teamRoundTrip($photo);
        $this->assertNull($photo->photoTags()->sole()->picked_up);
    }

    public function test_modal_edit_keeps_school_approval_and_updates_public_metrics(): void
    {
        Event::fake([SchoolDataApproved::class]);
        $photo = $this->schoolPhoto([$this->fullTag(null)]);
        $this->actingAs($this->teacher)->postJson('/api/teams/photos/approve', [
            'team_id' => $this->schoolTeam->id, 'photo_ids' => [$photo->id],
        ])->assertOk();
        $photo->refresh();
        $approval = $photo->team_approved_at->toDateTimeString();
        $xp = $this->student->fresh()->xp;
        $photoXp = $photo->xp;
        $this->teamRoundTrip($photo);
        $photo->refresh();
        $this->assertTrue((bool) $photo->is_public);
        $this->assertSame(2, $photo->verified->value);
        $this->assertSame($approval, $photo->team_approved_at->toDateTimeString());
        $this->assertSame((int) $xp + $photo->xp - $photoXp, (int) $this->student->fresh()->xp);
    }

    public function test_sequential_replacements_keep_one_complete_set(): void
    {
        foreach (['admin', 'team'] as $editor) {
            $photo = $editor === 'admin' ? $this->ownerPhoto([$this->fullTag()]) : $this->schoolPhoto([$this->fullTag()]);
            $r = $this->{$editor . 'RoundTrip'}($photo);
            $once = $this->shape($photo);
            if ($editor === 'admin') {
                $this->actingAs($this->admin)->postJson('/api/admin/contentsupdatedelete', ['photoId' => $photo->id, 'tags' => $r['payload']])->assertOk();
            } else {
                $this->actingAs($this->teacher)->patchJson("/api/teams/photos/{$photo->id}/tags", ['tags' => $r['payload']])->assertOk();
            }
            $this->assertSame($once, $this->shape($photo));
        }
    }

    // ---------- every editor ----------

    public function test_historical_observations_survive_all_editors_between_migrations(): void
    {
        $this->seed(GenerateTagsSeeder::class);
        $category = Category::where('key', 'other')->firstOrFail();
        $historical = LitterObject::where('key', 'randomLitter')->firstOrFail();
        $tags = [$this->fullTag(null), ['category_id' => $category->id, 'object' => ['id' => $historical->id], 'quantity' => 2, 'picked_up' => false]];
        $this->roundTripEachEditor($tags, function (Photo $photo, array $before, array $r, string $editor) {
            $this->assertSame($this->expectedAfterEdit($before), $this->shape($photo), $editor);
        });
    }

    public function test_combined_custom_rows_and_mixed_extras_keep_frontend_backend_xp(): void
    {
        $tags = [
            ['custom' => true, 'key' => 'first', 'custom_tags' => ['second'], 'quantity' => 2, 'picked_up' => null],
            ['material_only' => true, 'material' => ['id' => $this->materialId], 'brands' => [['id' => $this->brandId, 'quantity' => 7]], 'custom_tags' => ['mixed'], 'quantity' => 3, 'picked_up' => false],
            ['brand_only' => true, 'brand' => ['id' => $this->brandId], 'materials' => [$this->materialId], 'custom_tags' => ['brand-note'], 'quantity' => 4, 'picked_up' => true],
        ];
        $this->roundTripEachEditor($tags, function (Photo $photo, array $before, array $r, string $editor) {
            $this->assertSame($this->expectedAfterEdit($before), $this->shape($photo), $editor);
            $this->assertSame($r['xp'], (int) $photo->fresh()->xp, $editor);
        });
    }

    public function test_new_custom_cards_remain_separate_and_award_seven_xp(): void
    {
        $r = $this->runBridge(['cards' => [
            ['custom' => true, 'key' => 'separate', 'quantity' => 2, 'pickedUp' => null],
            ['custom' => true, 'key' => 'separate', 'quantity' => 5, 'pickedUp' => false],
        ]]);
        $photo = $this->ownerPhoto($r['payload']);
        $this->assertSame(7, $r['xp']);
        $this->assertSame(7, (int) $photo->xp);
        $this->assertSame([2, 5], $photo->photoTags()->orderBy('id')->pluck('quantity')->all());
        $r = $this->bridge($this->webApi($photo), null);
        $this->actingAs($this->owner)->putJson('/api/v3/tags', ['photo_id' => $photo->id, 'tags' => $r['payload']])->assertOk();
        $this->assertSame(7, (int) $photo->fresh()->xp);
        $this->assertCount(2, $this->shape($photo));
    }

    public function test_type_weighted_xp_matches_the_editor_for_each_dumping_size(): void
    {
        $this->seed(GenerateTagsSeeder::class);
        $clo = CategoryObject::where('litter_object_id', LitterObject::where('key', 'dumping')->value('id'))->sole();
        $tags = [];
        foreach (['small', 'medium', 'large'] as $key) {
            $tags[] = ['category_litter_object_id' => $clo->id,
                'litter_object_type_id' => LitterObjectType::where('key', $key)->value('id'), 'quantity' => 2, 'picked_up' => true];
        }
        $this->assertSame(200, (int) $this->ownerPhoto($tags)->xp);
        $this->roundTripEachEditor($tags, function (Photo $photo, array $before, array $r, string $editor) {
            $this->assertSame(245, $r['xp'], $editor);
            $this->assertSame($r['xp'], (int) $photo->fresh()->xp, $editor);
        });
    }
}
