<?php

namespace Tests\Feature\Teams;

use App\Enums\VerificationStatus;
use App\Events\SchoolDataApproved;
use App\Events\TagsVerifiedByAdmin;
use App\Models\Photo;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CategoryObject;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Teams\Team;
use App\Models\Teams\TeamType;
use App\Models\Users\User;
use App\Services\Metrics\MetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TeamPhotosTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected User $student;
    protected Team $schoolTeam;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create school team type
        $schoolType = TeamType::firstOrCreate(
            ['team' => 'school'],
            ['team' => 'school']
        );

        // Create teacher with school_manager role
        $this->teacher = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'manage school team', 'guard_name' => 'web']);
        $role->givePermissionTo('manage school team');
        $this->teacher->assignRole('school_manager');

        // Create school team
        $this->schoolTeam = Team::factory()->create([
            'type_id' => $schoolType->id,
            'leader' => $this->teacher->id,
            'safeguarding' => true,
        ]);

        $this->schoolTeam->users()->attach($this->teacher->id);

        // Create student
        $this->student = User::factory()->create();
        $this->schoolTeam->users()->attach($this->student->id);

        // Tag taxonomy (needed for tag editing tests)
        $smokingCat = Category::firstOrCreate(['key' => 'smoking']);
        $alcoholCat = Category::firstOrCreate(['key' => 'alcohol']);
        $unclassifiedCat = Category::firstOrCreate(['key' => 'unclassified']);
        $cigaretteButt = LitterObject::firstOrCreate(['key' => 'cigarette_butt']);
        $beerCan = LitterObject::firstOrCreate(['key' => 'beer_can']);
        $otherObj = LitterObject::firstOrCreate(['key' => 'other']);

        // CLO pivots for tag creation
        CategoryObject::firstOrCreate(['category_id' => $smokingCat->id, 'litter_object_id' => $cigaretteButt->id]);
        CategoryObject::firstOrCreate(['category_id' => $alcoholCat->id, 'litter_object_id' => $beerCan->id]);
        CategoryObject::firstOrCreate(['category_id' => $unclassifiedCat->id, 'litter_object_id' => $otherObj->id]);
    }

    // ─── Enum Tests ─────────────────────────────────

    public function test_verification_enum_values()
    {
        $this->assertEquals(0, VerificationStatus::UNVERIFIED->value);
        $this->assertEquals(1, VerificationStatus::VERIFIED->value);
        $this->assertEquals(2, VerificationStatus::ADMIN_APPROVED->value);
        $this->assertEquals(3, VerificationStatus::BBOX_APPLIED->value);
        $this->assertEquals(4, VerificationStatus::BBOX_VERIFIED->value);
        $this->assertEquals(5, VerificationStatus::AI_READY->value);
    }

    public function test_verification_enum_public_ready()
    {
        $this->assertFalse(VerificationStatus::UNVERIFIED->isPublicReady());
        $this->assertFalse(VerificationStatus::VERIFIED->isPublicReady());
        $this->assertTrue(VerificationStatus::ADMIN_APPROVED->isPublicReady());
        $this->assertTrue(VerificationStatus::AI_READY->isPublicReady());
    }

    public function test_verification_enum_ai_ready()
    {
        $this->assertFalse(VerificationStatus::ADMIN_APPROVED->isAiReady());
        $this->assertFalse(VerificationStatus::BBOX_VERIFIED->isAiReady());
        $this->assertTrue(VerificationStatus::AI_READY->isAiReady());
    }

    public function test_verification_enum_labels()
    {
        $this->assertEquals('Unverified', VerificationStatus::UNVERIFIED->label());
        $this->assertEquals('AI Ready', VerificationStatus::AI_READY->label());
    }

    // ─── Privacy: School photos are private by default ────────

    public function test_school_team_photos_are_private_by_default()
    {
        $photo = Photo::factory()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->schoolTeam->id,
        ]);

        // PhotoObserver should set is_public = false
        // If observer isn't registered, this tests the expected state
        $this->assertFalse((bool) $photo->fresh()->is_public);
    }

    public function test_non_school_team_photos_are_public_by_default()
    {
        $communityType = TeamType::firstOrCreate(
            ['team' => 'community'],
            ['team' => 'community', 'price' => 0]
        );

        $communityTeam = Team::factory()->create([
            'type_id' => $communityType->id,
            'leader' => $this->teacher->id,
        ]);

        $photo = Photo::factory()->create([
            'user_id' => $this->teacher->id,
            'team_id' => $communityTeam->id,
        ]);

        $this->assertTrue((bool) $photo->fresh()->is_public);
    }

    // ─── Photo Listing (members only) ─────────────────

    public function test_team_member_can_list_photos()
    {
        Photo::factory()->count(3)->create([
            'user_id' => $this->student->id,
            'team_id' => $this->schoolTeam->id,
            'is_public' => false,
        ]);

        $response = $this->actingAs($this->student)
            ->getJson('/api/teams/photos?team_id=' . $this->schoolTeam->id);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'photos.data');
    }

    public function test_non_member_cannot_list_team_photos()
    {
        $outsider = User::factory()->create();

        $response = $this->actingAs($outsider)
            ->getJson('/api/teams/photos?team_id=' . $this->schoolTeam->id);

        $response->assertStatus(403)
            ->assertJsonPath('message', 'not-a-member');
    }

    public function test_photo_list_filters_by_status()
    {
        // 2 pending, 1 approved
        Photo::factory()->count(2)->create([
            'user_id' => $this->student->id,
            'team_id' => $this->schoolTeam->id,
            'is_public' => false,
            'team_approved_at' => null,
        ]);

        Photo::factory()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->schoolTeam->id,
            'is_public' => true,
            'team_approved_at' => now(),
        ]);

        $pending = $this->actingAs($this->teacher)
            ->getJson('/api/teams/photos?team_id=' . $this->schoolTeam->id . '&status=pending');

        $pending->assertJsonCount(2, 'photos.data');

        $approved = $this->actingAs($this->teacher)
            ->getJson('/api/teams/photos?team_id=' . $this->schoolTeam->id . '&status=approved');

        $approved->assertJsonCount(1, 'photos.data');
    }

    // ─── Approval Flow ────────────────────────────────

    public function test_teacher_can_approve_specific_photos()
    {
        Event::fake([SchoolDataApproved::class, TagsVerifiedByAdmin::class]);

        $photos = Photo::factory()->count(3)->create([
            'user_id' => $this->student->id,
            'team_id' => $this->schoolTeam->id,
            'is_public' => false,
            'verified' => VerificationStatus::VERIFIED->value,
            'summary' => json_encode(['smoking' => ['cigarette_butt' => 1]]),
        ]);

        $response = $this->actingAs($this->teacher)
            ->postJson('/api/teams/photos/approve', [
                'team_id' => $this->schoolTeam->id,
                'photo_ids' => [$photos[0]->id, $photos[1]->id],
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('approved_count', 2);

        // Photos 0 and 1 are now public + admin approved
        $this->assertTrue((bool) $photos[0]->fresh()->is_public);
        $this->assertTrue((bool) $photos[1]->fresh()->is_public);
        $this->assertNotNull($photos[0]->fresh()->team_approved_at);
        $this->assertEquals($this->teacher->id, $photos[0]->fresh()->team_approved_by);
        $this->assertEquals(
            VerificationStatus::ADMIN_APPROVED->value,
            $photos[0]->fresh()->verified->value ?? $photos[0]->fresh()->verified
        );

        // Photo 2 still private
        $this->assertFalse((bool) $photos[2]->fresh()->is_public);

        // SchoolDataApproved fires (notifications)
        Event::assertDispatched(SchoolDataApproved::class);

        // TagsVerifiedByAdmin fires for each approved photo (MetricsService)
        Event::assertDispatched(TagsVerifiedByAdmin::class, 2);
    }

    public function test_teacher_can_approve_all_pending()
    {
        Event::fake([SchoolDataApproved::class, TagsVerifiedByAdmin::class]);

        Photo::factory()->count(5)->create([
            'user_id' => $this->student->id,
            'team_id' => $this->schoolTeam->id,
            'is_public' => false,
            'verified' => VerificationStatus::VERIFIED->value,
            'summary' => json_encode(['smoking' => ['cigarette_butt' => 1]]),
        ]);

        $response = $this->actingAs($this->teacher)
            ->postJson('/api/teams/photos/approve', [
                'team_id' => $this->schoolTeam->id,
                'approve_all' => true,
            ]);

        $response->assertOk()
            ->assertJsonPath('approved_count', 5);

        $publicCount = Photo::where('team_id', $this->schoolTeam->id)
            ->where('is_public', true)
            ->count();

        $this->assertEquals(5, $publicCount);

        // TagsVerifiedByAdmin fires for each → MetricsService processes all 5
        Event::assertDispatched(TagsVerifiedByAdmin::class, 5);
    }

    public function test_student_cannot_approve_photos()
    {
        $photo = Photo::factory()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->schoolTeam->id,
            'is_public' => false,
        ]);

        $response = $this->actingAs($this->student)
            ->postJson('/api/teams/photos/approve', [
                'team_id' => $this->schoolTeam->id,
                'photo_ids' => [$photo->id],
            ]);

        $response->assertStatus(403);
    }

    // ─── Tag Editing (CLO format) ────────────────────

    public function test_teacher_can_edit_tags_with_clo_format()
    {
        $smokingCloId = CategoryObject::whereHas('category', fn($q) => $q->where('key', 'smoking'))
            ->whereHas('litterObject', fn($q) => $q->where('key', 'cigarette_butt'))
            ->value('id');

        $alcoholCloId = CategoryObject::whereHas('category', fn($q) => $q->where('key', 'alcohol'))
            ->whereHas('litterObject', fn($q) => $q->where('key', 'beer_can'))
            ->value('id');

        $photo = Photo::factory()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->schoolTeam->id,
            'is_public' => false,
        ]);

        $response = $this->actingAs($this->teacher)
            ->patchJson("/api/teams/photos/{$photo->id}/tags", [
                'tags' => [
                    ['category_litter_object_id' => $smokingCloId, 'quantity' => 5, 'picked_up' => true],
                    ['category_litter_object_id' => $alcoholCloId, 'quantity' => 2, 'picked_up' => false],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $fresh = $photo->fresh();
        $this->assertEquals(7, $fresh->total_tags);
        // XP: Objects: 5×1 + 2×1 = 7, plus picked_up bonus: 5×5 = 25 → total 32
        $this->assertEquals(32, $fresh->xp);
        $this->assertCount(2, $fresh->photoTags);

        // Response includes new_tags
        $response->assertJsonStructure([
            'photo' => [
                'new_tags' => [
                    '*' => ['id', 'category_litter_object_id', 'quantity', 'picked_up', 'category', 'object'],
                ],
            ],
        ]);
    }

    public function test_student_cannot_edit_tags_on_team_photo()
    {
        $smokingCloId = CategoryObject::whereHas('category', fn($q) => $q->where('key', 'smoking'))
            ->whereHas('litterObject', fn($q) => $q->where('key', 'cigarette_butt'))
            ->value('id');

        $photo = Photo::factory()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->schoolTeam->id,
            'is_public' => false,
        ]);

        $response = $this->actingAs($this->student)
            ->patchJson("/api/teams/photos/{$photo->id}/tags", [
                'tags' => [
                    ['category_litter_object_id' => $smokingCloId, 'quantity' => 1],
                ],
            ]);

        $response->assertStatus(403);
    }

    // ─── Team Map (private) ───────────────────────────

    public function test_member_can_view_team_map_points()
    {
        Photo::factory()->count(3)->create([
            'user_id' => $this->student->id,
            'team_id' => $this->schoolTeam->id,
            'is_public' => false,
            'lat' => 51.8979,
            'lon' => -8.4706,
        ]);

        $response = $this->actingAs($this->student)
            ->getJson('/api/teams/photos/map?team_id=' . $this->schoolTeam->id);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'points');
    }

    public function test_non_member_cannot_view_team_map()
    {
        $outsider = User::factory()->create();

        $response = $this->actingAs($outsider)
            ->getJson('/api/teams/photos/map?team_id=' . $this->schoolTeam->id);

        $response->assertStatus(403);
    }

    // ─── new_tags Format in Index ────────────────────

    public function test_team_photos_index_returns_new_tags_format()
    {
        $smokingCloId = CategoryObject::whereHas('category', fn($q) => $q->where('key', 'smoking'))
            ->whereHas('litterObject', fn($q) => $q->where('key', 'cigarette_butt'))
            ->value('id');

        $photo = Photo::factory()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->schoolTeam->id,
            'is_public' => false,
        ]);

        // Add a tag via the action so photo has real photoTags
        app(\App\Actions\Tags\AddTagsToPhotoAction::class)->run(
            $this->student->id,
            $photo->id,
            [['category_litter_object_id' => $smokingCloId, 'quantity' => 3, 'picked_up' => true]]
        );

        $response = $this->actingAs($this->teacher)
            ->getJson('/api/teams/photos?team_id=' . $this->schoolTeam->id);

        $response->assertOk();

        $photos = $response->json('photos.data');
        $this->assertNotEmpty($photos);
        $this->assertArrayHasKey('new_tags', $photos[0]);
        $this->assertNotEmpty($photos[0]['new_tags']);
        $this->assertArrayHasKey('category_litter_object_id', $photos[0]['new_tags'][0]);
        $this->assertArrayHasKey('category', $photos[0]['new_tags'][0]);
        $this->assertArrayHasKey('object', $photos[0]['new_tags'][0]);
        $this->assertEquals('smoking', $photos[0]['new_tags'][0]['category']['key']);
        $this->assertEquals('cigarette_butt', $photos[0]['new_tags'][0]['object']['key']);
    }

    public function test_show_returns_new_tags_format()
    {
        $smokingCloId = CategoryObject::whereHas('category', fn($q) => $q->where('key', 'smoking'))
            ->whereHas('litterObject', fn($q) => $q->where('key', 'cigarette_butt'))
            ->value('id');

        $photo = Photo::factory()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->schoolTeam->id,
            'is_public' => false,
        ]);

        app(\App\Actions\Tags\AddTagsToPhotoAction::class)->run(
            $this->student->id,
            $photo->id,
            [['category_litter_object_id' => $smokingCloId, 'quantity' => 2]]
        );

        $response = $this->actingAs($this->teacher)
            ->getJson("/api/teams/photos/{$photo->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'photo' => [
                    'new_tags' => [
                        '*' => ['id', 'category_litter_object_id', 'quantity', 'category', 'object'],
                    ],
                ],
            ]);
    }

    // ─── Member Stats ─────────────────────────────────

    public function test_member_stats_returns_per_student_counts()
    {
        // Student has 3 photos: 2 pending, 1 approved
        Photo::factory()->count(2)->create([
            'user_id' => $this->student->id,
            'team_id' => $this->schoolTeam->id,
            'is_public' => false,
            'team_approved_at' => null,
            'total_tags' => 5,
        ]);

        Photo::factory()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->schoolTeam->id,
            'is_public' => true,
            'team_approved_at' => now(),
            'total_tags' => 10,
        ]);

        $response = $this->actingAs($this->teacher)
            ->getJson('/api/teams/photos/member-stats?team_id=' . $this->schoolTeam->id);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $members = $response->json('members');
        $this->assertCount(1, $members); // Only student (leader excluded)

        $studentStats = $members[0];
        $this->assertEquals($this->student->id, $studentStats['user_id']);
        $this->assertEquals(3, $studentStats['total_photos']);
        $this->assertEquals(2, $studentStats['pending']);
        $this->assertEquals(1, $studentStats['approved']);
        $this->assertEquals(20, $studentStats['litter_count']); // 2×5 + 1×10
    }

    public function test_member_stats_applies_safeguarding()
    {
        Photo::factory()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->schoolTeam->id,
            'is_public' => false,
        ]);

        $response = $this->actingAs($this->teacher)
            ->getJson('/api/teams/photos/member-stats?team_id=' . $this->schoolTeam->id);

        $response->assertOk();

        $members = $response->json('members');
        // Safeguarding is enabled, so names are pseudonyms
        $this->assertStringStartsWith('Student', $members[0]['name']);
        $this->assertNull($members[0]['username']);
    }

    public function test_member_stats_shows_real_names_without_safeguarding()
    {
        // Disable safeguarding
        $this->schoolTeam->update(['safeguarding' => false]);

        Photo::factory()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->schoolTeam->id,
            'is_public' => false,
        ]);

        $response = $this->actingAs($this->teacher)
            ->getJson('/api/teams/photos/member-stats?team_id=' . $this->schoolTeam->id);

        $response->assertOk();

        $members = $response->json('members');
        $this->assertEquals($this->student->name, $members[0]['name']);
        $this->assertEquals($this->student->username, $members[0]['username']);
    }

    public function test_member_stats_requires_leader_or_permission()
    {
        $response = $this->actingAs($this->student)
            ->getJson('/api/teams/photos/member-stats?team_id=' . $this->schoolTeam->id);

        $response->assertStatus(403);
    }

    // ─── Dashboard with Verification Breakdown ────────

    public function test_dashboard_returns_verification_breakdown()
    {
        // Create photos with different verification statuses
        Photo::factory()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->schoolTeam->id,
            'verified' => VerificationStatus::UNVERIFIED->value,
        ]);
        Photo::factory()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->schoolTeam->id,
            'verified' => VerificationStatus::VERIFIED->value,
        ]);
        Photo::factory()->count(2)->create([
            'user_id' => $this->student->id,
            'team_id' => $this->schoolTeam->id,
            'verified' => VerificationStatus::ADMIN_APPROVED->value,
            'total_tags' => 10,
        ]);

        $response = $this->actingAs($this->teacher)
            ->getJson('/api/teams/data?team_id=' . $this->schoolTeam->id . '&period=all');

        $response->assertOk()
            ->assertJsonPath('photos_count', 4)
            ->assertJsonPath('litter_count', 20) // 2 admin_approved × 10 total_tags
            ->assertJsonPath('verification.unverified', 1)
            ->assertJsonPath('verification.verified', 1)
            ->assertJsonPath('verification.admin_approved', 2);
    }

    // ─── MetricsService Integration ─────────────────

    /**
     * Critical: School teams must NOT be trusted.
     *
     * If a school team is trusted, TagsVerifiedByAdmin fires on tag creation
     * (before teacher approval), which writes to MetricsService global/country/state
     * totals. The photo is hidden from the map (is_public=false) but aggregate data
     * leaks — Ireland's litter count goes up before the teacher even reviews the data.
     *
     * Teacher approval IS the verification event for school photos.
     */
    public function test_approval_fires_tags_verified_for_metrics()
    {
        Event::fake([TagsVerifiedByAdmin::class, SchoolDataApproved::class]);

        $photo = Photo::factory()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->schoolTeam->id,
            'is_public' => false,
            'verified' => VerificationStatus::VERIFIED->value,
            'total_tags' => 5,
            'summary' => json_encode(['smoking' => ['cigarette_butt' => 5]]),
        ]);

        // Approve
        $this->actingAs($this->teacher)
            ->postJson('/api/teams/photos/approve', [
                'team_id' => $this->schoolTeam->id,
                'photo_ids' => [$photo->id],
            ])
            ->assertOk();

        // TagsVerifiedByAdmin fires with correct photo data
        Event::assertDispatched(TagsVerifiedByAdmin::class, function ($event) use ($photo) {
            return $event->photo_id === $photo->id
                && $event->user_id === $photo->user_id
                && $event->team_id === $this->schoolTeam->id;
        });

        // Photo is now ADMIN_APPROVED (not just VERIFIED)
        $this->assertEquals(
            VerificationStatus::ADMIN_APPROVED->value,
            $photo->fresh()->verified->value ?? $photo->fresh()->verified
        );
    }

    // ─── Points API Exclusion ─────────────────────────

    public function test_private_school_photos_excluded_from_public_scope()
    {
        // School team photo (private)
        Photo::factory()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->schoolTeam->id,
            'is_public' => false,
            'verified' => VerificationStatus::ADMIN_APPROVED->value,
        ]);

        // Regular photo (public)
        Photo::factory()->create([
            'user_id' => $this->teacher->id,
            'team_id' => null,
            'is_public' => true,
            'verified' => VerificationStatus::ADMIN_APPROVED->value,
        ]);

        // Public scope should only return the public photo
        $publicPhotos = Photo::public()
            ->where('verified', '>=', VerificationStatus::ADMIN_APPROVED->value)
            ->count();

        $this->assertEquals(1, $publicPhotos);

        // After approval, both should appear
        Photo::where('team_id', $this->schoolTeam->id)->update([
            'is_public' => true,
            'team_approved_at' => now(),
        ]);

        $publicPhotos = Photo::public()
            ->where('verified', '>=', VerificationStatus::ADMIN_APPROVED->value)
            ->count();

        $this->assertEquals(2, $publicPhotos);
    }

    // ─── Safeguarding in Photo List ───────────────────

    public function test_student_names_masked_in_safeguarded_team_photo_list()
    {
        Photo::factory()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->schoolTeam->id,
            'is_public' => false,
        ]);

        // Student sees masked names
        $response = $this->actingAs($this->student)
            ->getJson('/api/teams/photos?team_id=' . $this->schoolTeam->id);

        $response->assertOk();

        $photos = $response->json('photos.data');
        $this->assertStringStartsWith('Student', $photos[0]['user']['name']);
    }

    public function test_teacher_sees_real_names_in_photo_list()
    {
        Photo::factory()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->schoolTeam->id,
            'is_public' => false,
        ]);

        $response = $this->actingAs($this->teacher)
            ->getJson('/api/teams/photos?team_id=' . $this->schoolTeam->id);

        $response->assertOk();

        $photos = $response->json('photos.data');
        // Teacher is leader, so should see real names
        $this->assertEquals($this->student->name, $photos[0]['user']['name']);
    }

    // ─── Delete ──────────────────────────────────────

    public function test_teacher_can_delete_team_photo()
    {
        Storage::fake('s3');
        Storage::fake('bbox');

        $photo = Photo::factory()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->schoolTeam->id,
            'is_public' => false,
        ]);

        // Set student XP
        $this->student->update(['xp' => 10]);

        $response = $this->actingAs($this->teacher)
            ->deleteJson("/api/teams/photos/{$photo->id}", [
                'team_id' => $this->schoolTeam->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        // Soft-deleted
        $this->assertSoftDeleted('photos', ['id' => $photo->id]);

        // Student XP unchanged (no processed_xp → no XP change)
        $this->student->refresh();
        $this->assertEquals(10, $this->student->xp);
    }

    public function test_teacher_can_delete_processed_photo_with_metrics_reversal()
    {
        Storage::fake('s3');
        Storage::fake('bbox');

        $photo = Photo::factory()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->schoolTeam->id,
            'is_public' => true,
            'verified' => VerificationStatus::ADMIN_APPROVED->value,
            'processed_at' => now(),
            'processed_fp' => 'abc123',
            'processed_tags' => json_encode(['objects' => [1 => 3], 'materials' => [], 'brands' => [], 'custom_tags' => []]),
            'processed_xp' => 5,
        ]);

        $this->student->update(['xp' => 20]);

        $response = $this->actingAs($this->teacher)
            ->deleteJson("/api/teams/photos/{$photo->id}", [
                'team_id' => $this->schoolTeam->id,
            ]);

        $response->assertOk();

        $this->assertSoftDeleted('photos', ['id' => $photo->id]);

        // XP reversed on student
        $this->student->refresh();
        $this->assertEquals(15, $this->student->xp); // 20 - 5
    }

    public function test_student_cannot_delete_team_photo()
    {
        $photo = Photo::factory()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->schoolTeam->id,
            'is_public' => false,
        ]);

        $response = $this->actingAs($this->student)
            ->deleteJson("/api/teams/photos/{$photo->id}", [
                'team_id' => $this->schoolTeam->id,
            ]);

        $response->assertStatus(403);

        // Not deleted
        $this->assertDatabaseHas('photos', ['id' => $photo->id, 'deleted_at' => null]);
    }

    // ─── Revoke ──────────────────────────────────────

    public function test_revoke_approval_makes_photos_private()
    {
        Event::fake([TagsVerifiedByAdmin::class, SchoolDataApproved::class]);

        // Create photo then approve it (bypasses observer setting is_public=false)
        $photo = Photo::factory()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->schoolTeam->id,
            'verified' => VerificationStatus::VERIFIED->value,
        ]);

        // Simulate teacher approval
        $photo->update([
            'is_public' => true,
            'verified' => VerificationStatus::ADMIN_APPROVED->value,
            'team_approved_at' => now(),
            'team_approved_by' => $this->teacher->id,
        ]);

        $this->assertTrue((bool) $photo->fresh()->is_public);

        // Revoke
        $response = $this->actingAs($this->teacher)
            ->postJson('/api/teams/photos/revoke', [
                'team_id' => $this->schoolTeam->id,
                'photo_ids' => [$photo->id],
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('revoked_count', 1);

        $photo->refresh();
        $this->assertFalse((bool) $photo->is_public);
        $this->assertNull($photo->team_approved_at);
        $this->assertNull($photo->team_approved_by);
        $this->assertEquals(VerificationStatus::VERIFIED->value, $photo->verified->value ?? $photo->verified);
    }

    public function test_revoke_reverses_metrics()
    {
        Event::fake([TagsVerifiedByAdmin::class, SchoolDataApproved::class]);

        $photo = Photo::factory()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->schoolTeam->id,
            'verified' => VerificationStatus::VERIFIED->value,
        ]);

        // Simulate approved + processed state
        $photo->update([
            'is_public' => true,
            'verified' => VerificationStatus::ADMIN_APPROVED->value,
            'team_approved_at' => now(),
            'team_approved_by' => $this->teacher->id,
            'processed_at' => now(),
            'processed_fp' => 'def456',
            'processed_tags' => json_encode(['objects' => [1 => 5], 'materials' => [], 'brands' => [], 'custom_tags' => []]),
            'processed_xp' => 8,
        ]);

        $response = $this->actingAs($this->teacher)
            ->postJson('/api/teams/photos/revoke', [
                'team_id' => $this->schoolTeam->id,
                'photo_ids' => [$photo->id],
            ]);

        $response->assertOk()
            ->assertJsonPath('revoked_count', 1);

        // MetricsService clears processed_* fields
        $photo->refresh();
        $this->assertNull($photo->processed_at);
        $this->assertNull($photo->processed_xp);
    }

    public function test_revoke_is_idempotent()
    {
        Event::fake([TagsVerifiedByAdmin::class, SchoolDataApproved::class]);

        // Photo already private (never approved or already revoked)
        $photo = Photo::factory()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->schoolTeam->id,
            'is_public' => false,
            'verified' => VerificationStatus::VERIFIED->value,
            'team_approved_at' => null,
        ]);

        $response = $this->actingAs($this->teacher)
            ->postJson('/api/teams/photos/revoke', [
                'team_id' => $this->schoolTeam->id,
                'photo_ids' => [$photo->id],
            ]);

        $response->assertOk()
            ->assertJsonPath('revoked_count', 0);
    }

    public function test_student_cannot_revoke()
    {
        $photo = Photo::factory()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->schoolTeam->id,
            'verified' => VerificationStatus::VERIFIED->value,
        ]);

        // Simulate approved state
        $photo->update([
            'is_public' => true,
            'verified' => VerificationStatus::ADMIN_APPROVED->value,
            'team_approved_at' => now(),
            'team_approved_by' => $this->teacher->id,
        ]);

        $response = $this->actingAs($this->student)
            ->postJson('/api/teams/photos/revoke', [
                'team_id' => $this->schoolTeam->id,
                'photo_ids' => [$photo->id],
            ]);

        $response->assertStatus(403);

        // Still public
        $this->assertTrue((bool) $photo->fresh()->is_public);
    }

    // ─── Safeguarding on Global Map ──────────────────

    public function test_safeguarding_masks_student_on_global_map()
    {
        // Create and approve a school photo so it appears on global map
        $photo = Photo::factory()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->schoolTeam->id,
            'verified' => VerificationStatus::VERIFIED->value,
            'lat' => 53.3498,
            'lon' => -6.2603,
        ]);

        // Simulate approval (bypass observer)
        $photo->update([
            'is_public' => true,
            'verified' => VerificationStatus::ADMIN_APPROVED->value,
            'team_approved_at' => now(),
            'team_approved_by' => $this->teacher->id,
        ]);

        // Global points API — zoom >= 15, tight bbox around the photo
        $response = $this->getJson('/api/points?' . http_build_query([
            'bbox' => ['left' => -6.27, 'right' => -6.25, 'bottom' => 53.34, 'top' => 53.36],
            'zoom' => 16,
        ]));

        $response->assertOk();

        $features = $response->json('features');
        $this->assertNotEmpty($features);

        $props = $features[0]['properties'];
        $this->assertNull($props['name']);
        $this->assertNull($props['username']);
        $this->assertNull($props['social']);
        // Team name IS shown
        $this->assertEquals($this->schoolTeam->name, $props['team']);
    }
}
