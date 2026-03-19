<?php

namespace Tests\Feature\Teams;

use App\Enums\VerificationStatus;
use App\Events\SchoolDataApproved;
use App\Events\TagsVerifiedByAdmin;
use App\Events\TeamCreated;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Photo;
use App\Models\Teams\Team;
use App\Models\Teams\TeamType;
use App\Models\Users\User;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * End-to-end lifecycle test: create school team → join as student → upload → teacher approves.
 *
 * Validates the full LitterWeek flow through the API layer.
 */
class SchoolTeamLifecycleTest extends TestCase
{
    private int $schoolTypeId;
    private int $communityTypeId;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->schoolTypeId = TeamType::firstOrCreate(
            ['team' => 'school'],
            ['price' => 0, 'description' => 'School']
        )->id;

        $this->communityTypeId = TeamType::firstOrCreate(
            ['team' => 'community'],
            ['price' => 0, 'description' => 'Community']
        )->id;

        $permissions = collect([
            'create school team', 'manage school team',
            'toggle safeguarding', 'view student identities',
        ])->map(fn ($name) => Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));

        $role = Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);
        $role->syncPermissions($permissions);
    }

    private function createSchoolManager(array $overrides = []): User
    {
        $user = User::factory()->create(array_merge([
            'remaining_teams' => 1,
        ], $overrides));
        $user->assignRole('school_manager');

        return $user;
    }

    /**
     * Full lifecycle: teacher creates school team → student joins → student uploads + tags → teacher approves.
     */
    public function test_full_school_team_lifecycle(): void
    {
        // ── Step 1: Teacher creates school team ──

        $teacher = $this->createSchoolManager();

        $createResponse = $this->actingAs($teacher)->postJson('/api/teams/create', [
            'name' => 'Curraghboy NS',
            'identifier' => 'CurraghboyNS2026',
            'teamType' => $this->schoolTypeId,
            'contact_email' => 'teacher@curraghboyns.ie',
            'county' => 'Roscommon',
        ]);

        $createResponse->assertOk()
            ->assertJsonPath('success', true);

        $team = Team::where('name', 'Curraghboy NS')->first();
        $this->assertNotNull($team);
        $this->assertTrue((bool) $team->safeguarding, 'School team must have safeguarding enabled');
        $this->assertFalse((bool) $team->is_trusted, 'School team must NOT be trusted');
        $this->assertEquals($teacher->id, $team->leader);

        // Teacher's remaining_teams decremented
        $this->assertEquals(0, $teacher->fresh()->remaining_teams);

        // Teacher is a member with active team set
        $this->assertTrue($teacher->fresh()->isMemberOfTeam($team->id));
        $this->assertEquals($team->id, $teacher->fresh()->active_team);

        // ── Step 2: Student joins via identifier ──

        $student = User::factory()->create(['name' => 'Test Student']);

        $joinResponse = $this->actingAs($student)->postJson('/api/teams/join', [
            'identifier' => 'CurraghboyNS2026',
        ]);

        $joinResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('team.name', 'Curraghboy NS');

        $this->assertTrue($student->fresh()->isMemberOfTeam($team->id));
        $this->assertEquals($team->id, $student->fresh()->active_team);
        $this->assertEquals(2, $team->fresh()->members);

        // ── Step 3: Student uploads photo (simulated) + tags ──

        $photo = Photo::factory()->create([
            'user_id' => $student->id,
            'team_id' => $team->id,
            'verified' => VerificationStatus::UNVERIFIED->value,
            'is_public' => false, // PhotoObserver sets this for school teams
            'lat' => 53.6312,
            'lon' => -8.0890,
        ]);

        // Create tags
        $category = Category::firstOrCreate(['key' => 'smoking']);
        $object = LitterObject::firstOrCreate(['key' => 'cigarette_butt']);
        $cloId = $this->getCloId($category->id, $object->id);

        PhotoTag::create([
            'photo_id' => $photo->id,
            'category_litter_object_id' => $cloId,
            'category_id' => $category->id,
            'litter_object_id' => $object->id,
            'quantity' => 5,
            'picked_up' => true,
        ]);

        $photo->update([
            'total_tags' => 5,
            'verified' => VerificationStatus::VERIFIED->value,
            'summary' => json_encode(['smoking' => ['cigarette_butt' => 5]]),
            'xp' => 10,
        ]);

        // Photo must be private
        $photo->refresh();
        $this->assertFalse((bool) $photo->is_public);
        $this->assertNull($photo->team_approved_at);

        // Not visible in public queries
        $this->assertEquals(0, Photo::where('is_public', true)
            ->where('verified', '>=', VerificationStatus::ADMIN_APPROVED->value)
            ->count());

        // ── Step 4: Teacher sees pending photos ──

        $photosResponse = $this->actingAs($teacher)
            ->getJson('/api/teams/photos?team_id=' . $team->id . '&status=pending');

        $photosResponse->assertOk();
        $pending = $photosResponse->json('photos.data');
        $this->assertCount(1, $pending);
        $this->assertEquals($photo->id, $pending[0]['id']);

        // ── Step 5: Teacher approves ──

        Event::fake([TagsVerifiedByAdmin::class, SchoolDataApproved::class]);

        $approveResponse = $this->actingAs($teacher)
            ->postJson('/api/teams/photos/approve', [
                'team_id' => $team->id,
                'photo_ids' => [$photo->id],
            ]);

        $approveResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('approved_count', 1);

        // ── Step 6: Photo is now public + ADMIN_APPROVED ──

        $photo->refresh();
        $this->assertTrue((bool) $photo->is_public);
        $this->assertEquals(
            VerificationStatus::ADMIN_APPROVED->value,
            is_object($photo->verified) ? $photo->verified->value : $photo->verified
        );
        $this->assertNotNull($photo->team_approved_at);
        $this->assertEquals($teacher->id, $photo->team_approved_by);

        // Visible in public queries now
        $this->assertEquals(1, Photo::where('is_public', true)
            ->where('verified', '>=', VerificationStatus::ADMIN_APPROVED->value)
            ->count());

        // MetricsService event fired
        Event::assertDispatched(TagsVerifiedByAdmin::class, function ($event) use ($photo, $team) {
            return $event->photo_id === $photo->id
                && $event->user_id === $photo->user_id
                && $event->team_id === $team->id;
        });

        Event::assertDispatched(SchoolDataApproved::class);
    }

    /**
     * Regular user cannot create school teams (403).
     */
    public function test_regular_user_cannot_create_school_team(): void
    {
        $user = User::factory()->create(['remaining_teams' => 5]);

        $this->actingAs($user)->postJson('/api/teams/create', [
            'name' => 'Unauthorized School',
            'identifier' => 'NoAuth1',
            'teamType' => $this->schoolTypeId,
            'contact_email' => 'teacher@school.ie',
        ])->assertStatus(403);

        $this->assertDatabaseMissing('teams', ['name' => 'Unauthorized School']);
    }

    /**
     * Regular user CAN create community teams (with remaining_teams > 0).
     */
    public function test_regular_user_can_create_community_team(): void
    {
        $user = User::factory()->create(['remaining_teams' => 1]);

        $response = $this->actingAs($user)->postJson('/api/teams/create', [
            'name' => 'Community Cleanup',
            'identifier' => 'CommClean2026',
            'teamType' => $this->communityTypeId,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $team = Team::where('name', 'Community Cleanup')->first();
        $this->assertNotNull($team);
        $this->assertFalse((bool) $team->safeguarding);
        $this->assertEquals(0, $user->fresh()->remaining_teams);
    }

    /**
     * Safeguarding masks student identity in member listing.
     */
    public function test_safeguarding_masks_names_in_member_listing(): void
    {
        $teacher = $this->createSchoolManager();

        $team = Team::factory()->create([
            'type_id' => $this->schoolTypeId,
            'leader' => $teacher->id,
            'safeguarding' => true,
            'is_trusted' => false,
        ]);
        $team->users()->attach($teacher->id);

        $student1 = User::factory()->create(['name' => 'Alice Smith']);
        $student2 = User::factory()->create(['name' => 'Bob Jones']);
        $team->users()->attach($student1->id);
        $team->users()->attach($student2->id);

        // Student sees masked names
        $response = $this->actingAs($student1)
            ->getJson('/api/teams/members?team_id=' . $team->id);

        $response->assertOk();
        $members = collect($response->json('result.data'));

        // Students should see pseudonyms for other students
        $studentNames = $members
            ->filter(fn ($m) => $m['id'] !== $teacher->id)
            ->pluck('name');

        foreach ($studentNames as $name) {
            $this->assertMatchesRegularExpression('/^Student \d+$/', $name,
                "Student name should be masked: got '$name'");
        }

        // Teacher sees real names
        $response = $this->actingAs($teacher)
            ->getJson('/api/teams/members?team_id=' . $team->id);

        $members = collect($response->json('result.data'));
        $names = $members->pluck('name');

        $this->assertContains('Alice Smith', $names->toArray());
        $this->assertContains('Bob Jones', $names->toArray());
    }

    /**
     * Join → leave → active team cleared.
     */
    public function test_join_leave_clears_active_team(): void
    {
        $teacher = $this->createSchoolManager();

        $team = Team::factory()->create([
            'type_id' => $this->communityTypeId,
            'leader' => $teacher->id,
        ]);
        $team->users()->attach($teacher->id);

        $user = User::factory()->create();

        // Join
        $this->actingAs($user)->postJson('/api/teams/join', [
            'identifier' => $team->identifier,
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertTrue($user->fresh()->isMemberOfTeam($team->id));
        $this->assertEquals($team->id, $user->fresh()->active_team);

        // Leave
        $this->actingAs($user)->postJson('/api/teams/leave', [
            'team_id' => $team->id,
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertFalse($user->fresh()->isMemberOfTeam($team->id));
        $this->assertNull($user->fresh()->active_team);
    }

    /**
     * Team privacy settings can be updated per-team.
     */
    public function test_privacy_settings_update(): void
    {
        $teacher = $this->createSchoolManager();

        $team = Team::factory()->create([
            'type_id' => $this->communityTypeId,
            'leader' => $teacher->id,
        ]);
        $team->users()->attach($teacher->id);

        $response = $this->actingAs($teacher)
            ->postJson('/api/teams/settings', [
                'team_id' => $team->id,
                'all' => false,
                'settings' => [
                    'show_name_maps' => false,
                    'show_username_maps' => true,
                    'show_name_leaderboards' => false,
                    'show_username_leaderboards' => true,
                ],
            ]);

        $response->assertOk()->assertJsonPath('success', true);

        $pivot = $teacher->teams()->where('team_id', $team->id)->first()->pivot;
        $this->assertFalse((bool) $pivot->show_name_maps);
        $this->assertTrue((bool) $pivot->show_username_maps);
        $this->assertFalse((bool) $pivot->show_name_leaderboards);
        $this->assertTrue((bool) $pivot->show_username_leaderboards);
    }

    /**
     * Second team creation blocked when remaining_teams is 0.
     */
    public function test_team_limit_enforced(): void
    {
        $teacher = $this->createSchoolManager(['remaining_teams' => 1]);

        // First team — success
        $this->actingAs($teacher)->postJson('/api/teams/create', [
            'name' => 'First Team',
            'identifier' => 'First1',
            'teamType' => $this->schoolTypeId,
            'contact_email' => 'teacher@school.ie',
            'county' => 'Galway',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertEquals(0, $teacher->fresh()->remaining_teams);

        // Second team — blocked
        $this->actingAs($teacher)->postJson('/api/teams/create', [
            'name' => 'Second Team',
            'identifier' => 'Second1',
            'teamType' => $this->schoolTypeId,
            'contact_email' => 'teacher@school.ie',
            'county' => 'Galway',
        ])->assertOk()->assertJsonPath('success', false)->assertJsonPath('msg', 'max-created');

        $this->assertDatabaseMissing('teams', ['name' => 'Second Team']);
    }
}
