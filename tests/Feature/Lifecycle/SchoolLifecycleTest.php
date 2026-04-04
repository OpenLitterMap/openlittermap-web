<?php

namespace Tests\Feature\Lifecycle;

use App\Enums\LocationType;
use App\Enums\VerificationStatus;
use App\Enums\XpScore;
use App\Events\SchoolDataApproved;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CategoryObject;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Photo;
use App\Models\Teams\Participant;
use App\Models\Teams\Team;
use App\Models\Teams\TeamType;
use App\Models\Users\User;
use App\Services\Redis\RedisKeys;
use Database\Seeders\Tags\GenerateTagsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Feature\HasPhotoUploads;
use Tests\TestCase;

/**
 * School pipeline lifecycle: full LitterWeek journey with real API calls.
 *
 * No Event::fake(), no MetricsService mocks — tests the real pipeline.
 *
 * Confirms:
 * - Upload does NOT award XP for school photos (is_public=false gate)
 * - Student tagging does NOT fire TagsVerifiedByAdmin (no tag metrics before approval)
 * - Teacher approval fires TagsVerifiedByAdmin → MetricsService::processPhoto() → doCreate()
 * - Teacher edit + re-approve updates metrics correctly
 * - Teacher delete reverses all metrics
 * - Participant session uploads attributed to facilitator
 */
class SchoolLifecycleTest extends TestCase
{
    use HasPhotoUploads;

    private User $teacher;
    private User $student;
    private Team $schoolTeam;
    private ?CategoryObject $buttsClo = null;
    private ?CategoryObject $wrappersClo = null;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Fake only SchoolDataApproved (ShouldBroadcast serialization fails in tests).
        // TagsVerifiedByAdmin fires naturally → ProcessPhotoMetrics → MetricsService.
        Event::fake([SchoolDataApproved::class]);

        $this->setUpPhotoUploads();
        $this->seed(GenerateTagsSeeder::class);

        // Resolve CLOs
        $smokingCategory = Category::where('key', 'smoking')->first();
        $buttsObject = LitterObject::where('key', 'butts')->first();
        $this->buttsClo = CategoryObject::where('category_id', $smokingCategory->id)
            ->where('litter_object_id', $buttsObject->id)
            ->first();

        $foodCategory = Category::where('key', 'food')->first();
        $wrappersObject = LitterObject::where('key', 'wrapper')->first();
        if ($foodCategory && $wrappersObject) {
            $this->wrappersClo = CategoryObject::where('category_id', $foodCategory->id)
                ->where('litter_object_id', $wrappersObject->id)
                ->first();
        }

        // School team setup
        $schoolType = TeamType::firstOrCreate(
            ['team' => 'school'],
            ['team' => 'school']
        );

        $this->teacher = User::factory()->create([
            'name' => 'Ms. Murphy',
            'xp' => 0,

            'verification_required' => true,
            'picked_up' => null,
        ]);

        $role = Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'manage school team', 'guard_name' => 'web']);
        $role->givePermissionTo('manage school team');
        $this->teacher->assignRole('school_manager');

        $this->schoolTeam = Team::factory()->create([
            'type_id' => $schoolType->id,
            'leader' => $this->teacher->id,
            'safeguarding' => true,
            'is_trusted' => false,
        ]);

        $this->schoolTeam->users()->attach($this->teacher->id);

        $this->student = User::factory()->create([
            'name' => 'Alice Student',
            'xp' => 0,

            'verification_required' => true,
            'active_team' => $this->schoolTeam->id,
            'picked_up' => null,
        ]);

        $this->schoolTeam->users()->attach($this->student->id);
    }

    // =========================================================================
    // Test 1: Upload does NOT award XP for school photos
    // =========================================================================

    /**
     * recordUploadMetrics() is gated on is_public. School photos (is_public=false)
     * skip upload XP, metrics, and leaderboard entries entirely. All metrics are
     * deferred until teacher approval.
     */
    public function test_upload_does_not_award_xp_for_school_student(): void
    {
        $photoId = $this->uploadPhoto($this->student);
        $photo = Photo::find($photoId);

        // Photo is private (PhotoObserver sets is_public=false for school teams)
        $this->assertFalse((bool) $photo->is_public, 'School photo must be private');

        // Student has NO XP — upload metrics skipped for school photos
        $this->student->refresh();
        $this->assertEquals(0, $this->student->xp, 'Student has 0 XP before approval');

        // No metrics row exists
        $this->assertNoMetricsRow($this->student->id);

        // Redis: student NOT on leaderboard
        $globalScope = RedisKeys::global();
        $this->assertFalse(
            Redis::zScore(RedisKeys::xpRanking($globalScope), (string) $this->student->id),
            'Student not on leaderboard before approval'
        );

        // processed_at is null — processPhoto() will route to doCreate() at approval
        $this->assertNull($photo->processed_at);
    }

    // =========================================================================
    // Test 2: Tagging does NOT fire metrics for school student
    // =========================================================================

    /**
     * After tagging, a school student photo has verified=VERIFIED(1) and
     * is_public=false. TagsVerifiedByAdmin does NOT fire — no metrics
     * are processed. Upload metrics were also skipped (is_public=false).
     */
    public function test_tagging_does_not_process_tag_metrics(): void
    {
        $photoId = $this->uploadPhoto($this->student);
        $this->tagPhoto($this->student, $photoId, 3); // 3 butts → 3 object XP

        $photo = Photo::find($photoId);

        // verified = VERIFIED (1) for school students
        $this->assertEquals(
            VerificationStatus::VERIFIED->value,
            $photo->verified->value,
            'School student photo verified=VERIFIED(1) after tagging'
        );

        // Photo is still private
        $this->assertFalse((bool) $photo->is_public);

        // Summary + XP are generated (AddTagsToPhotoAction works regardless of trust)
        $this->assertNotNull($photo->summary, 'Summary generated at tag time');
        $this->assertGreaterThan(0, $photo->xp, 'Tag XP calculated');

        // No metrics at all — upload skipped, tags not processed
        $this->assertNull($photo->processed_at, 'processed_at still null');

        // Student XP is 0 — no upload XP, no tag XP
        $this->student->refresh();
        $this->assertEquals(0, $this->student->xp, 'Student XP = 0 (no metrics processed)');

        // No metrics row
        $this->assertNoMetricsRow($this->student->id);
    }

    // =========================================================================
    // Test 3: Teacher approval triggers MetricsService
    // =========================================================================

    /**
     * Teacher approves → photo becomes public, verified=ADMIN_APPROVED,
     * TagsVerifiedByAdmin fires → MetricsService::processPhoto() → doCreate().
     * Student gets full XP (upload + tag) at approval time — nothing before.
     */
    public function test_teacher_approval_processes_tag_metrics(): void
    {
        $uploadXp = XpScore::Upload->xp();

        $photoId = $this->uploadPhoto($this->student);
        $this->tagPhoto($this->student, $photoId, 3); // 3 butts

        $photo = Photo::find($photoId);
        $tagXp = $photo->xp; // Tag XP only (no upload base)

        // Pre-approval: NO XP at all (upload metrics deferred for school)
        $this->student->refresh();
        $this->assertEquals(0, $this->student->xp, 'Student has 0 XP before approval');

        // Teacher approves
        $response = $this->actingAs($this->teacher)
            ->postJson('/api/teams/photos/approve', [
                'team_id' => $this->schoolTeam->id,
                'photo_ids' => [$photoId],
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('approved_count', 1);

        $photo->refresh();

        // Photo is now public + ADMIN_APPROVED
        $this->assertTrue((bool) $photo->is_public, 'Photo public after approval');
        $this->assertEquals(
            VerificationStatus::ADMIN_APPROVED->value,
            $photo->verified->value,
            'Photo ADMIN_APPROVED after approval'
        );

        // MetricsService::processPhoto → doCreate: full XP (upload + tag)
        $expectedTotalXp = $uploadXp + $tagXp;
        $this->assertEquals($expectedTotalXp, (int) $photo->processed_xp, 'processed_xp = upload + tag XP');

        // Student XP = full amount (doCreate increments users.xp)
        $this->student->refresh();
        $this->assertEquals($expectedTotalXp, $this->student->xp, 'Student XP = upload + tag after approval');

        // Metrics: 1 upload, full XP, 3 litter
        $this->assertMetricsRow($this->student->id, 1, $expectedTotalXp, 3);

        // Redis leaderboard updated
        $globalScope = RedisKeys::global();
        $this->assertEquals(
            (float) $expectedTotalXp,
            Redis::zScore(RedisKeys::xpRanking($globalScope), (string) $this->student->id),
            'Leaderboard XP = upload + tag after approval'
        );

        // Location metrics: country has data now
        $countryScope = RedisKeys::country($photo->country_id);
        $this->assertGreaterThan(0, (int) Redis::hGet(RedisKeys::stats($countryScope), 'litter'));
    }

    // =========================================================================
    // Test 4: Teacher edits tags before approval
    // =========================================================================

    /**
     * Teacher uses PATCH /api/teams/photos/{photo}/tags to change tags,
     * then approves. MetricsService processes the teacher's tags, not the student's.
     */
    public function test_teacher_edits_tags_then_approves(): void
    {
        $uploadXp = XpScore::Upload->xp();

        $photoId = $this->uploadPhoto($this->student);
        $this->tagPhoto($this->student, $photoId, 2); // Student tags 2 butts

        $photo = Photo::find($photoId);
        $originalTagXp = $photo->xp;

        // Teacher edits: replaces with 5 butts
        $editResponse = $this->actingAs($this->teacher)
            ->patchJson("/api/teams/photos/{$photoId}/tags", [
                'tags' => [
                    ['category_litter_object_id' => $this->buttsClo->id, 'quantity' => 5],
                ],
            ]);

        $editResponse->assertOk();

        $photo->refresh();
        $editedTagXp = $photo->xp; // New tag XP after edit
        $this->assertGreaterThan($originalTagXp, $editedTagXp, 'Teacher edit increased tag XP');

        // Still not approved — no metrics at all (upload deferred)
        $this->assertNull($photo->processed_at, 'processed_at null before approval');

        // Teacher approves
        $this->actingAs($this->teacher)
            ->postJson('/api/teams/photos/approve', [
                'team_id' => $this->schoolTeam->id,
                'photo_ids' => [$photoId],
            ])
            ->assertOk();

        $photo->refresh();
        $expectedTotalXp = $uploadXp + $editedTagXp;
        $this->assertEquals($expectedTotalXp, (int) $photo->processed_xp, 'processed_xp reflects teacher edits');

        $this->student->refresh();
        $this->assertEquals($expectedTotalXp, $this->student->xp, 'Student XP reflects teacher-edited tags');
        $this->assertMetricsRow($this->student->id, 1, $expectedTotalXp, 5);
    }

    // =========================================================================
    // Test 5: Teacher deletes unapproved photo
    // =========================================================================

    /**
     * Teacher deletes a tagged-but-unapproved photo.
     * No metrics were ever recorded (upload deferred, tags not processed),
     * so deletePhoto() is a no-op. Just soft-deletes the photo.
     */
    public function test_teacher_deletes_unapproved_photo(): void
    {
        $photoId = $this->uploadPhoto($this->student);
        $this->tagPhoto($this->student, $photoId, 3);

        // Student has 0 XP (upload metrics deferred)
        $this->student->refresh();
        $this->assertEquals(0, $this->student->xp);

        // Teacher deletes
        $response = $this->actingAs($this->teacher)
            ->deleteJson("/api/teams/photos/{$photoId}", [
                'team_id' => $this->schoolTeam->id,
            ]);

        $response->assertOk();
        $this->assertDatabaseMissing('photos', ['id' => $photoId]);

        // Student XP unchanged (was already 0)
        $this->student->refresh();
        $this->assertEquals(0, $this->student->xp, 'Student XP = 0 after teacher delete');

        // No metrics row (nothing was ever recorded)
        $this->assertNoMetricsRow($this->student->id);

        // Redis: not on leaderboard
        $globalScope = RedisKeys::global();
        $this->assertFalse(
            Redis::zScore(RedisKeys::xpRanking($globalScope), (string) $this->student->id),
            'Not on leaderboard after delete'
        );
    }

    // =========================================================================
    // Test 6: Teacher deletes approved photo
    // =========================================================================

    /**
     * Full cycle: upload → tag → approve → delete.
     * Delete must reverse BOTH upload XP and tag XP.
     */
    public function test_teacher_deletes_approved_photo(): void
    {
        $uploadXp = XpScore::Upload->xp();

        $photoId = $this->uploadPhoto($this->student);
        $this->tagPhoto($this->student, $photoId, 3);

        // Approve — doCreate processes full XP
        $this->actingAs($this->teacher)
            ->postJson('/api/teams/photos/approve', [
                'team_id' => $this->schoolTeam->id,
                'photo_ids' => [$photoId],
            ])
            ->assertOk();

        $photo = Photo::find($photoId);
        $totalXp = (int) $photo->processed_xp;
        $this->assertGreaterThan($uploadXp, $totalXp, 'processed_xp includes tag XP');

        $this->student->refresh();
        $this->assertEquals($totalXp, $this->student->xp);

        // Teacher deletes
        $this->actingAs($this->teacher)
            ->deleteJson("/api/teams/photos/{$photoId}", [
                'team_id' => $this->schoolTeam->id,
            ])
            ->assertOk();

        $this->assertDatabaseMissing('photos', ['id' => $photoId]);

        // All XP reversed
        $this->student->refresh();
        $this->assertEquals(0, $this->student->xp, 'All XP reversed after deleting approved photo');
        $this->assertMetricsRow($this->student->id, 0, 0, 0);

        // Pruned from leaderboard
        $globalScope = RedisKeys::global();
        $this->assertFalse(
            Redis::zScore(RedisKeys::xpRanking($globalScope), (string) $this->student->id),
            'Pruned from leaderboard'
        );
    }

    // =========================================================================
    // Test 7: Revoke reverses tag metrics, re-approve restores
    // =========================================================================

    /**
     * Upload → tag → approve → revoke → re-approve.
     * Revoke calls MetricsService::deletePhoto() → reverses ALL metrics.
     * Re-approval fires TagsVerifiedByAdmin → doCreate() recalculates
     * and restores users.xp (doCreate now increments users.xp).
     */
    public function test_revoke_reverses_metrics_reapprove_restores(): void
    {
        $uploadXp = XpScore::Upload->xp();

        $photoId = $this->uploadPhoto($this->student);
        $this->tagPhoto($this->student, $photoId, 3);

        // Approve — doCreate processes full XP
        $this->actingAs($this->teacher)
            ->postJson('/api/teams/photos/approve', [
                'team_id' => $this->schoolTeam->id,
                'photo_ids' => [$photoId],
            ])
            ->assertOk();

        $photo = Photo::find($photoId);
        $approvedXp = (int) $photo->processed_xp;

        $this->student->refresh();
        $this->assertEquals($approvedXp, $this->student->xp);

        // Revoke
        $this->actingAs($this->teacher)
            ->postJson('/api/teams/photos/revoke', [
                'team_id' => $this->schoolTeam->id,
                'photo_ids' => [$photoId],
            ])
            ->assertOk();

        $photo->refresh();

        // Photo is private again, verified downgraded to VERIFIED
        $this->assertFalse((bool) $photo->is_public, 'Private after revoke');
        $this->assertEquals(
            VerificationStatus::VERIFIED->value,
            $photo->verified->value,
            'Verified downgraded to VERIFIED after revoke'
        );

        // Metrics reversed — processed_at cleared by deletePhoto()
        $this->assertNull($photo->processed_at, 'processed_at cleared after revoke');

        // Student XP = 0 — deletePhoto reversed the full processed_xp
        $this->student->refresh();
        $this->assertEquals(0, $this->student->xp, 'Student XP = 0 after revoke');

        // Re-approve
        $this->actingAs($this->teacher)
            ->postJson('/api/teams/photos/approve', [
                'team_id' => $this->schoolTeam->id,
                'photo_ids' => [$photoId],
            ])
            ->assertOk();

        $photo->refresh();

        // Photo public again
        $this->assertTrue((bool) $photo->is_public, 'Public after re-approve');

        // processPhoto → doCreate (processed_at was null after revoke)
        // doCreate now increments users.xp — revoke+reapprove fully restores XP
        $this->assertNotNull($photo->processed_at, 'processed_at set after re-approve');

        $this->student->refresh();
        $this->assertEquals($approvedXp, $this->student->xp, 'Student XP fully restored after re-approve');

        $metricsXp = $this->getMetricsXp($this->student->id);
        $this->assertEquals($approvedXp, $metricsXp, 'Metrics XP fully restored after re-approve');
    }

    // =========================================================================
    // Test 8: Multi-student class scenario
    // =========================================================================

    /**
     * 3 students upload and tag. Teacher batch-approves all.
     * Verify each student gets correct individual XP and the aggregate is correct.
     */
    public function test_multi_student_batch_approval(): void
    {
        $uploadXp = XpScore::Upload->xp();

        $student2 = User::factory()->create([
            'xp' => 0,

            'verification_required' => true,
            'active_team' => $this->schoolTeam->id,
            'picked_up' => null,
        ]);
        $this->schoolTeam->users()->attach($student2->id);

        $student3 = User::factory()->create([
            'xp' => 0,

            'verification_required' => true,
            'active_team' => $this->schoolTeam->id,
            'picked_up' => null,
        ]);
        $this->schoolTeam->users()->attach($student3->id);

        // Each student uploads + tags
        $photo1Id = $this->uploadPhoto($this->student);
        $this->tagPhoto($this->student, $photo1Id, 3); // 3 butts

        $photo2Id = $this->uploadPhoto($student2);
        $this->tagPhoto($student2, $photo2Id, 2); // 2 butts

        $photo3Id = $this->uploadPhoto($student3);
        $this->tagPhoto($student3, $photo3Id, 5); // 5 butts

        // Pre-approval: each student has 0 XP (upload deferred for school)
        $this->student->refresh();
        $student2->refresh();
        $student3->refresh();
        $this->assertEquals(0, $this->student->xp);
        $this->assertEquals(0, $student2->xp);
        $this->assertEquals(0, $student3->xp);

        // Teacher batch approves all
        $response = $this->actingAs($this->teacher)
            ->postJson('/api/teams/photos/approve', [
                'team_id' => $this->schoolTeam->id,
                'approve_all' => true,
            ]);

        $response->assertOk()
            ->assertJsonPath('approved_count', 3);

        // Each student's XP is upload + their individual tag XP
        $photo1 = Photo::find($photo1Id);
        $photo2 = Photo::find($photo2Id);
        $photo3 = Photo::find($photo3Id);

        $this->student->refresh();
        $student2->refresh();
        $student3->refresh();

        $this->assertEquals(
            $uploadXp + $photo1->xp,
            $this->student->xp,
            'Student 1 XP = upload + 3-butts tag XP'
        );
        $this->assertEquals(
            $uploadXp + $photo2->xp,
            $student2->xp,
            'Student 2 XP = upload + 2-butts tag XP'
        );
        $this->assertEquals(
            $uploadXp + $photo3->xp,
            $student3->xp,
            'Student 3 XP = upload + 5-butts tag XP'
        );

        // All photos are public + ADMIN_APPROVED
        foreach ([$photo1, $photo2, $photo3] as $photo) {
            $photo->refresh();
            $this->assertTrue((bool) $photo->is_public);
            $this->assertEquals(VerificationStatus::ADMIN_APPROVED->value, $photo->verified->value);
        }

        // Aggregate metrics: total litter = 3 + 2 + 5 = 10
        $aggregateLitter = DB::table('metrics')
            ->where('timescale', 0)
            ->where('location_type', LocationType::Global->value)
            ->where('location_id', 0)
            ->where('user_id', 0) // aggregate row
            ->value('litter');

        $this->assertEquals(10, (int) $aggregateLitter, 'Aggregate litter = 10 (3+2+5)');
    }

    // =========================================================================
    // Test 9: Double approval is idempotent (no double-counting)
    // =========================================================================

    /**
     * Approving an already-approved photo is a no-op.
     * MetricsService detects matching fingerprint and returns early.
     */
    public function test_double_approval_no_double_counting(): void
    {
        $photoId = $this->uploadPhoto($this->student);
        $this->tagPhoto($this->student, $photoId, 3);

        // First approval
        $this->actingAs($this->teacher)
            ->postJson('/api/teams/photos/approve', [
                'team_id' => $this->schoolTeam->id,
                'photo_ids' => [$photoId],
            ])
            ->assertJsonPath('approved_count', 1);

        $photo = Photo::find($photoId);
        $xpAfterFirst = (int) $photo->processed_xp;

        $this->student->refresh();
        $xpBeforeSecond = $this->student->xp;

        // Second approval — already approved, should be no-op
        $this->actingAs($this->teacher)
            ->postJson('/api/teams/photos/approve', [
                'team_id' => $this->schoolTeam->id,
                'photo_ids' => [$photoId],
            ])
            ->assertJsonPath('approved_count', 0);

        // XP unchanged
        $this->student->refresh();
        $this->assertEquals($xpBeforeSecond, $this->student->xp, 'XP unchanged after double approval');

        $photo->refresh();
        $this->assertEquals($xpAfterFirst, (int) $photo->processed_xp, 'processed_xp unchanged');
    }

    // =========================================================================
    // Test 10: Participant session upload — facilitator gets XP
    // =========================================================================

    /**
     * Participant uploads are attributed to the facilitator (teacher).
     * The participant's slot is tracked via participant_id.
     * Upload XP goes to the facilitator's account.
     */
    public function test_participant_upload_attributed_to_facilitator(): void
    {
        $uploadXp = XpScore::Upload->xp();

        // Enable participant sessions + create a slot
        $this->schoolTeam->update([
            'participant_sessions_enabled' => true,
            'max_participants' => 30,
        ]);

        $slotResponse = $this->actingAs($this->teacher)
            ->postJson("/api/teams/{$this->schoolTeam->id}/participants", [
                'count' => 1,
            ]);

        $slotResponse->assertOk();
        $token = $slotResponse->json('participants.0.session_token');
        $participantId = $slotResponse->json('participants.0.id');

        // Participant uploads via token auth
        $imageAttributes = $this->getImageAndAttributes();
        $uploadResponse = $this->postJson('/api/participant/upload', [
            'photo' => $imageAttributes['file'],
            'lat' => $imageAttributes['latitude'],
            'lon' => $imageAttributes['longitude'],
            'date' => $imageAttributes['dateTime']->timestamp,
            'model' => 'test model',
        ], [
            'X-Participant-Token' => $token,
        ]);

        $uploadResponse->assertOk();
        $photoId = $uploadResponse->json('photo_id');

        $photo = Photo::find($photoId);

        // Photo attributed to facilitator (teacher), not a student user
        $this->assertEquals($this->teacher->id, $photo->user_id, 'Photo owned by facilitator');
        $this->assertEquals($participantId, $photo->participant_id, 'Participant ID tracked');
        $this->assertEquals($this->schoolTeam->id, $photo->team_id, 'Team ID set');

        // Photo is private (school team)
        $this->assertFalse((bool) $photo->is_public, 'School photo private');

        // Teacher does NOT get upload XP (school photo, upload metrics deferred)
        $this->teacher->refresh();
        $this->assertEquals(0, $this->teacher->xp, 'Facilitator has 0 XP before approval (school photo)');

        // Student XP untouched
        $this->student->refresh();
        $this->assertEquals(0, $this->student->xp, 'Student XP unaffected by participant upload');
    }

    // =========================================================================
    // Test 11: Participant upload + tag + approve — full cycle
    // =========================================================================

    /**
     * Participant uploads, teacher tags from facilitator queue,
     * teacher approves. All XP attributed to facilitator.
     */
    public function test_participant_full_cycle_upload_tag_approve(): void
    {
        $uploadXp = XpScore::Upload->xp();

        $this->schoolTeam->update([
            'participant_sessions_enabled' => true,
            'max_participants' => 30,
        ]);

        $slotResponse = $this->actingAs($this->teacher)
            ->postJson("/api/teams/{$this->schoolTeam->id}/participants", [
                'count' => 1,
            ]);
        $token = $slotResponse->json('participants.0.session_token');

        // Participant uploads
        $imageAttributes = $this->getImageAndAttributes();
        $uploadResponse = $this->postJson('/api/participant/upload', [
            'photo' => $imageAttributes['file'],
            'lat' => $imageAttributes['latitude'],
            'lon' => $imageAttributes['longitude'],
            'date' => $imageAttributes['dateTime']->timestamp,
            'model' => 'test model',
        ], [
            'X-Participant-Token' => $token,
        ]);

        $photoId = $uploadResponse->json('photo_id');

        // Teacher tags the participant's photo
        // The photo's user_id is the teacher (facilitator), so teacher can tag it
        $this->tagPhoto($this->teacher, $photoId, 4); // 4 butts

        $photo = Photo::find($photoId);

        // School student tagging: verified = VERIFIED(1), no metrics fire
        $this->assertEquals(VerificationStatus::VERIFIED->value, $photo->verified->value);
        $this->assertNull($photo->processed_at, 'No metrics processed yet');

        // Teacher approves
        $this->actingAs($this->teacher)
            ->postJson('/api/teams/photos/approve', [
                'team_id' => $this->schoolTeam->id,
                'photo_ids' => [$photoId],
            ])
            ->assertJsonPath('approved_count', 1);

        $photo->refresh();
        $expectedXp = $uploadXp + $photo->xp;

        // Facilitator gets the XP
        $this->teacher->refresh();
        $this->assertEquals($expectedXp, $this->teacher->xp, 'Facilitator XP = upload + tag after participant approval');

        // Metrics attributed to facilitator
        $this->assertMetricsRow($this->teacher->id, 1, $expectedXp, 4);
    }

    // =========================================================================
    // Test 12: Photo not visible on public map before approval
    // =========================================================================

    /**
     * School photos must not appear in public queries before teacher approval.
     */
    public function test_school_photo_excluded_from_public_scope_before_approval(): void
    {
        $photoId = $this->uploadPhoto($this->student);
        $this->tagPhoto($this->student, $photoId, 3);

        // Photo::public() excludes school photos (is_public=false)
        $publicCount = Photo::public()
            ->where('verified', '>=', VerificationStatus::ADMIN_APPROVED->value)
            ->count();

        $this->assertEquals(0, $publicCount, 'No public photos before approval');

        // Approve
        $this->actingAs($this->teacher)
            ->postJson('/api/teams/photos/approve', [
                'team_id' => $this->schoolTeam->id,
                'photo_ids' => [$photoId],
            ])
            ->assertOk();

        // Now visible
        $publicCount = Photo::public()
            ->where('verified', '>=', VerificationStatus::ADMIN_APPROVED->value)
            ->count();

        $this->assertEquals(1, $publicCount, '1 public photo after approval');
    }

    // =========================================================================
    // Action Helpers
    // =========================================================================

    private function uploadPhoto(User $user): int
    {
        $imageAttributes = $this->getImageAndAttributes();

        $response = $this->actingAs($user)
            ->postJson('/api/v3/upload', [
                'photo' => $imageAttributes['file'],
                'lat' => $imageAttributes['latitude'],
                'lon' => $imageAttributes['longitude'],
                'date' => $imageAttributes['dateTime']->timestamp,
                'model' => 'test model',
            ]);

        $response->assertOk();

        return $response->json('photo_id');
    }

    private function tagPhoto(User $user, int $photoId, int $quantity): void
    {
        $response = $this->actingAs($user)
            ->postJson('/api/v3/tags', [
                'photo_id' => $photoId,
                'tags' => [
                    ['category_litter_object_id' => $this->buttsClo->id, 'quantity' => $quantity],
                ],
            ]);

        $response->assertOk();
    }

    // =========================================================================
    // Assertion Helpers
    // =========================================================================

    private function assertMetricsRow(int $userId, int $uploads, int $xp, int $litter): void
    {
        $row = DB::table('metrics')
            ->where('timescale', 0)
            ->where('location_type', LocationType::Global->value)
            ->where('location_id', 0)
            ->where('user_id', $userId)
            ->where('year', 0)
            ->where('month', 0)
            ->first();

        $this->assertNotNull($row, "All-time global per-user metrics row should exist for user {$userId}");
        $this->assertEquals($uploads, (int) $row->uploads, "Metrics uploads for user {$userId}");
        $this->assertEquals($xp, (int) $row->xp, "Metrics XP for user {$userId}");
        $this->assertEquals($litter, (int) $row->litter, "Metrics litter for user {$userId}");
    }

    private function assertNoMetricsRow(int $userId): void
    {
        $row = DB::table('metrics')
            ->where('timescale', 0)
            ->where('location_type', LocationType::Global->value)
            ->where('location_id', 0)
            ->where('user_id', $userId)
            ->where('year', 0)
            ->where('month', 0)
            ->first();

        $this->assertNull($row, "No metrics row should exist for user {$userId}");
    }

    private function getMetricsXp(int $userId): int
    {
        $row = DB::table('metrics')
            ->where('timescale', 0)
            ->where('location_type', LocationType::Global->value)
            ->where('location_id', 0)
            ->where('user_id', $userId)
            ->where('year', 0)
            ->where('month', 0)
            ->first();

        return $row ? (int) $row->xp : 0;
    }
}
