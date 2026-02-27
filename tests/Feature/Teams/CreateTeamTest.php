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

class CreateTeamTest extends TestCase
{
    use RefreshDatabase;

    private int $communityTypeId;
    private int $schoolTypeId;

    protected function setUp(): void
    {
        parent::setUp();

        // Reset Spatie's cached permissions between tests
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->communityTypeId = TeamType::create([
            'team' => 'community', 'price' => 0, 'description' => 'Community',
        ])->id;

        $this->schoolTypeId = TeamType::create([
            'team' => 'school', 'price' => 0, 'description' => 'School',
        ])->id;

        // Create school_manager role + permissions (web guard — matches User model default)
        $permissions = collect([
            'create school team',
            'manage school team',
            'toggle safeguarding',
            'view student identities',
        ])->map(fn ($name) => Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));

        $role = Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);
        $role->syncPermissions($permissions);
    }

    /**
     * Helper to create a school manager user with 1 remaining team.
     */
    private function createSchoolManager(array $overrides = []): User
    {
        $user = User::factory()->create(array_merge([
            'remaining_teams' => 1,
        ], $overrides));
        $user->assignRole('school_manager');

        return $user;
    }

    // ── Authorization — school_manager role required ──

    public function test_school_manager_can_create_a_community_team(): void
    {
        $manager = $this->createSchoolManager();

        $response = $this->actingAs($manager, 'api')->postJson('/api/teams/create', [
            'name' => 'Cork Litter Pickers',
            'identifier' => 'CorkLP2026',
            'teamType' => $this->communityTypeId,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('team.name', 'Cork Litter Pickers');

        $this->assertDatabaseHas('teams', [
            'name' => 'Cork Litter Pickers',
            'identifier' => 'CorkLP2026',
            'leader' => $manager->id,
            'safeguarding' => false,
        ]);

        $this->assertTrue($manager->fresh()->isMemberOfTeam(
            Team::where('name', 'Cork Litter Pickers')->value('id')
        ));

        $this->assertEquals(0, $manager->fresh()->remaining_teams);
    }

    public function test_regular_user_can_create_community_team(): void
    {
        $user = User::factory()->create(['remaining_teams' => 1]);
        // No school_manager role needed for community teams

        $response = $this->actingAs($user, 'api')->postJson('/api/teams/create', [
            'name' => 'Regular Team',
            'identifier' => 'Regular1',
            'teamType' => $this->communityTypeId,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('team.name', 'Regular Team');

        $this->assertDatabaseHas('teams', ['name' => 'Regular Team']);
        $this->assertEquals(0, $user->fresh()->remaining_teams);
    }

    public function test_regular_user_cannot_create_without_remaining_teams(): void
    {
        $user = User::factory()->create(['remaining_teams' => 0]);

        $response = $this->actingAs($user, 'api')->postJson('/api/teams/create', [
            'name' => 'No Quota Team',
            'identifier' => 'NoQuota1',
            'teamType' => $this->communityTypeId,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('msg', 'max-created');

        $this->assertDatabaseMissing('teams', ['name' => 'No Quota Team']);
    }

    public function test_school_manager_cannot_create_when_none_remaining(): void
    {
        $manager = $this->createSchoolManager(['remaining_teams' => 0]);

        $response = $this->actingAs($manager, 'api')->postJson('/api/teams/create', [
            'name' => 'No Quota',
            'identifier' => 'NoQuota1',
            'teamType' => $this->communityTypeId,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('msg', 'max-created');
    }

    public function test_create_validates_required_fields(): void
    {
        $manager = $this->createSchoolManager();

        $this->actingAs($manager, 'api')
            ->postJson('/api/teams/create', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'identifier', 'teamType']);
    }

    public function test_create_validates_unique_name_and_identifier(): void
    {
        $manager = $this->createSchoolManager();

        Team::create([
            'name' => 'Taken Name',
            'identifier' => 'TakenID',
            'type_id' => $this->communityTypeId,
            'type_name' => 'community',
            'leader' => $manager->id,
            'created_by' => $manager->id,
        ]);

        $this->actingAs($manager, 'api')
            ->postJson('/api/teams/create', [
                'name' => 'Taken Name',
                'identifier' => 'TakenID',
                'teamType' => $this->communityTypeId,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'identifier']);
    }

    public function test_unauthenticated_users_cannot_create_teams(): void
    {
        $this->postJson('/api/teams/create', [
            'name' => 'Ghost Team',
            'identifier' => 'Ghost1',
            'teamType' => $this->communityTypeId,
        ])->assertStatus(401);
    }

    // ── School Teams ──

    public function test_school_manager_can_create_a_school_team(): void
    {
        $teacher = $this->createSchoolManager();

        $response = $this->actingAs($teacher, 'api')->postJson('/api/teams/create', [
            'name' => 'Curraghboy NS',
            'identifier' => 'CurraghboyNS2026',
            'teamType' => $this->schoolTypeId,
            'contact_email' => 'teacher@curraghboyns.ie',
            'school_roll_number' => '19456A',
            'county' => 'Roscommon',
            'academic_year' => '2025/2026',
            'class_group' => '5th Class',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('team.name', 'Curraghboy NS');

        $this->assertDatabaseHas('teams', [
            'name' => 'Curraghboy NS',
            'safeguarding' => true,
            'school_roll_number' => '19456A',
            'contact_email' => 'teacher@curraghboyns.ie',
            'county' => 'Roscommon',
            'academic_year' => '2025/2026',
            'class_group' => '5th Class',
        ]);
    }

    public function test_school_teams_have_safeguarding_on_by_default(): void
    {
        $teacher = $this->createSchoolManager();

        $this->actingAs($teacher, 'api')->postJson('/api/teams/create', [
            'name' => 'Safe School',
            'identifier' => 'SafeSchool1',
            'teamType' => $this->schoolTypeId,
            'contact_email' => 'teacher@school.ie',
        ]);

        $this->assertTrue((bool) Team::where('name', 'Safe School')->value('safeguarding'));
    }

    public function test_regular_user_cannot_create_school_team(): void
    {
        $user = User::factory()->create(['remaining_teams' => 3]);
        // No school_manager role

        $this->actingAs($user, 'api')->postJson('/api/teams/create', [
            'name' => 'Unauthorized School',
            'identifier' => 'NoAuth1',
            'teamType' => $this->schoolTypeId,
            'contact_email' => 'teacher@school.ie',
        ])->assertStatus(403);

        $this->assertDatabaseMissing('teams', ['name' => 'Unauthorized School']);
    }

    public function test_school_team_requires_contact_email(): void
    {
        $teacher = $this->createSchoolManager();

        $this->actingAs($teacher, 'api')
            ->postJson('/api/teams/create', [
                'name' => 'No Email School',
                'identifier' => 'NoEmail1',
                'teamType' => $this->schoolTypeId,
                // Missing contact_email
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['contact_email']);
    }
}
