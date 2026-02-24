<?php

namespace Tests\Feature\Teams;

use App\Models\Teams\Team;
use App\Models\Teams\TeamType;
use App\Models\Users\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SafeguardingTest extends TestCase
{
    use RefreshDatabase;

    private Team $schoolTeam;
    private User $teacher;
    private array $students = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Reset Spatie's cached permissions between tests
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Permissions + role (web guard — matches User model default)
        $permissions = collect([
            'create school team',
            'manage school team',
            'toggle safeguarding',
            'view student identities',
        ])->map(fn ($name) => Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));

        $role = Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);
        $role->syncPermissions($permissions);

        $schoolType = TeamType::create(['team' => 'school', 'price' => 0, 'description' => 'School']);

        $this->teacher = User::factory()->create(['name' => 'Ms. Murphy', 'username' => 'msmurphy']);
        $this->teacher->assignRole('school_manager');

        $this->schoolTeam = Team::create([
            'name' => 'Curraghboy NS - 5th Class',
            'identifier' => 'Curraghboy5th',
            'type_id' => $schoolType->id,
            'type_name' => 'school',
            'leader' => $this->teacher->id,
            'created_by' => $this->teacher->id,
            'safeguarding' => true,
            'contact_email' => 'teacher@curraghboyns.ie',
        ]);

        $this->teacher->teams()->attach($this->schoolTeam->id);

        for ($i = 1; $i <= 3; $i++) {
            $student = User::factory()->create([
                'name' => "Student Name {$i}",
                'username' => "student{$i}",
            ]);
            $student->teams()->attach($this->schoolTeam->id);
            $this->students[] = $student;
        }

        $this->schoolTeam->update(['members' => 4]);
    }

    public function test_teacher_sees_real_names_as_team_leader()
    {
        $response = $this->actingAs($this->teacher, 'api')->getJson('/api/teams/members?' . http_build_query([
                'team_id' => $this->schoolTeam->id,
            ]));

        $response->assertOk();
        $members = collect($response->json('result.data'));

        $this->assertTrue(
            $members->contains(fn ($m) => $m['name'] === 'Ms. Murphy'),
            'Teacher should see their own real name'
        );
        $this->assertTrue(
            $members->contains(fn ($m) => $m['name'] === 'Student Name 1'),
            'Teacher should see student real names'
        );
    }

    public function test_students_see_masked_names()
    {
        $student = $this->students[0];

        $response = $this->actingAs($student, 'api')->getJson('/api/teams/members?' . http_build_query([
                'team_id' => $this->schoolTeam->id,
            ]));

        $response->assertOk();
        $members = collect($response->json('result.data'));

        $this->assertFalse(
            $members->contains(fn ($m) => $m['name'] === 'Student Name 1'),
            'Real student names should be hidden'
        );
        $this->assertFalse(
            $members->contains(fn ($m) => $m['name'] === 'Ms. Murphy'),
            'Teacher name should be hidden from students'
        );
        $this->assertTrue(
            $members->contains(fn ($m) => str_starts_with($m['name'], 'Student ')),
            'Should see "Student N" format'
        );
        $this->assertTrue(
            $members->every(fn ($m) => $m['username'] === null),
            'Usernames should be null'
        );
    }

    public function test_admin_with_permission_sees_real_names()
    {
        $admin = User::factory()->create(['name' => 'Admin User']);
        $admin->givePermissionTo('view student identities');
        $admin->teams()->attach($this->schoolTeam->id);
        $this->schoolTeam->increment('members');

        $response = $this->actingAs($admin, 'api')->getJson('/api/teams/members?' . http_build_query([
                'team_id' => $this->schoolTeam->id,
            ]));

        $response->assertOk();
        $members = collect($response->json('result.data'));

        $this->assertTrue(
            $members->contains(fn ($m) => $m['name'] === 'Student Name 1'),
            'Admin with permission should see real names'
        );
    }

    public function test_community_team_is_not_masked()
    {
        $communityType = TeamType::create(['team' => 'community', 'price' => 0, 'description' => 'Community']);

        $leader = User::factory()->create(['name' => 'Leader']);
        $member = User::factory()->create(['name' => 'Visible Member', 'username' => 'visible123']);

        $communityTeam = Team::create([
            'name' => 'Cork Cleanup Crew',
            'identifier' => 'CorkCrew',
            'type_id' => $communityType->id,
            'type_name' => 'community',
            'leader' => $leader->id,
            'created_by' => $leader->id,
            'safeguarding' => false,
        ]);

        $leader->teams()->attach($communityTeam->id, [
            'show_name_leaderboards' => true,
            'show_username_leaderboards' => true,
        ]);
        $member->teams()->attach($communityTeam->id, [
            'show_name_leaderboards' => true,
            'show_username_leaderboards' => true,
        ]);
        $communityTeam->update(['members' => 2]);

        $response = $this->actingAs($member, 'api')->getJson('/api/teams/members?' . http_build_query([
                'team_id' => $communityTeam->id,
            ]));

        $response->assertOk();
        $members = collect($response->json('result.data'));

        $this->assertTrue($members->contains(fn ($m) => $m['name'] === 'Visible Member'));
        $this->assertTrue($members->contains(fn ($m) => $m['username'] === 'visible123'));
    }

    public function test_leader_can_toggle_safeguarding()
    {
        $this->assertTrue((bool) $this->schoolTeam->safeguarding);

        $this->actingAs($this->teacher, 'api')->patchJson('/api/teams/update/' . $this->schoolTeam->id, [
            'name' => $this->schoolTeam->name,
            'identifier' => $this->schoolTeam->identifier,
            'safeguarding' => false,
        ]);

        $this->assertFalse((bool) $this->schoolTeam->fresh()->safeguarding);
    }

    public function test_non_leader_cannot_toggle_safeguarding()
    {
        $this->actingAs($this->students[0], 'api')
            ->patchJson('/api/teams/update/' . $this->schoolTeam->id, [
                'name' => $this->schoolTeam->name,
                'identifier' => $this->schoolTeam->identifier,
                'safeguarding' => false,
            ])
            ->assertStatus(403);

        $this->assertTrue((bool) $this->schoolTeam->fresh()->safeguarding);
    }
}
