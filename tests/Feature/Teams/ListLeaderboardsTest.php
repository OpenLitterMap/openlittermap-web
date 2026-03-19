<?php

namespace Tests\Feature\Teams;

use App\Models\Photo;
use App\Models\Teams\Team;
use App\Models\Teams\TeamType;
use App\Models\Users\User;
use Illuminate\Testing\Fluent\AssertableJson;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ListLeaderboardsTest extends TestCase
{
    /**
     * Create N photos for a team with the given total_tags.
     */
    private function createTeamPhotos(Team $team, int $count, int $totalTagsEach = 1): void
    {
        $user = User::factory()->create();
        Photo::factory($count)->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'total_tags' => $totalTagsEach,
        ]);
    }

    public function test_it_can_list_the_global_teams_leaderboards()
    {
        /** @var User $user */
        $user = User::factory()->create();

        $teams = Team::factory(3)->create(['members' => 1]);

        // Team 1: 1 photo, 1 tag each = 1 total tag
        $this->createTeamPhotos($teams[0], 1, 1);
        // Team 2: 2 photos, 1 tag each = 2 total tags
        $this->createTeamPhotos($teams[1], 2, 1);
        // Team 3: 1 photo, 3 tags = 3 total tags
        $this->createTeamPhotos($teams[2], 1, 3);

        $result = $this
            ->actingAs($user)
            ->getJson('/api/teams/leaderboard')
            ->assertOk()
            ->assertJsonPath('current_page', 1)
            ->assertJsonCount(3, 'data')
            ->assertJson(function (AssertableJson $json) {
                $json->has('data.0.name');
                $json->has('data.0.type_name');
                $json->has('data.0.total_members');
                $json->has('data.0.total_tags');
                $json->has('data.0.total_photos');
                $json->has('data.0.created_at');
                $json->missing('data.0.total_litter');
                $json->missing('data.0.total_images');
                $json->etc();
            })
            ->json();

        $this->assertEquals([3, 2, 1], array_column($result['data'], 'total_tags'));
    }

    public function test_it_does_not_include_teams_that_dont_want_to_be_in_leaderboards()
    {
        /** @var User $user */
        $user = User::factory()->create();

        $teams = Team::factory(3)->sequence(
            [],
            ['leaderboards' => false],
            [],
        )->create();

        $this->createTeamPhotos($teams[0], 1, 1);
        $this->createTeamPhotos($teams[1], 1, 2);
        $this->createTeamPhotos($teams[2], 1, 3);

        $result = $this
            ->actingAs($user)
            ->getJson('/api/teams/leaderboard')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->json();

        $this->assertEquals([3, 1], array_column($result['data'], 'total_tags'));
    }

    public function test_leaderboard_is_paginated()
    {
        /** @var User $user */
        $user = User::factory()->create();

        $teams = Team::factory(30)->create();
        foreach ($teams as $team) {
            $this->createTeamPhotos($team, 1, 1);
        }

        $this->actingAs($user)
            ->getJson('/api/teams/leaderboard')
            ->assertOk()
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('last_page', 2)
            ->assertJsonPath('total', 30)
            ->assertJsonCount(25, 'data');

        $this->actingAs($user)
            ->getJson('/api/teams/leaderboard?page=2')
            ->assertOk()
            ->assertJsonPath('current_page', 2)
            ->assertJsonCount(5, 'data');
    }

    public function test_school_teams_are_hidden_from_leaderboard_by_default()
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $schoolType = TeamType::create([
            'team' => 'school', 'price' => 0, 'description' => 'School',
        ]);

        $permissions = collect([
            'create school team', 'manage school team',
            'toggle safeguarding', 'view student identities',
        ])->map(fn ($name) => Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));

        $role = Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);
        $role->syncPermissions($permissions);

        $teacher = User::factory()->create(['remaining_teams' => 1]);
        $teacher->assignRole('school_manager');

        $this->actingAs($teacher)->postJson('/api/teams/create', [
            'name' => 'Hidden School',
            'identifier' => 'HiddenSchool1',
            'teamType' => $schoolType->id,
            'contact_email' => 'teacher@school.ie',
            'county' => 'Cork',
        ])->assertOk();

        $this->assertDatabaseHas('teams', [
            'name' => 'Hidden School',
            'leaderboards' => false,
        ]);

        // Verify it does not appear on the leaderboard
        $result = $this->actingAs($teacher)
            ->getJson('/api/teams/leaderboard')
            ->assertOk()
            ->json();

        $names = array_column($result['data'], 'name');
        $this->assertNotContains('Hidden School', $names);
    }

    public function test_community_teams_are_hidden_from_leaderboard_by_default()
    {
        $communityType = TeamType::create([
            'team' => 'community', 'price' => 0, 'description' => 'Community',
        ]);

        $user = User::factory()->create(['remaining_teams' => 1]);

        $this->actingAs($user)->postJson('/api/teams/create', [
            'name' => 'Private Community',
            'identifier' => 'PrivCom1',
            'teamType' => $communityType->id,
        ])->assertOk();

        $this->assertDatabaseHas('teams', [
            'name' => 'Private Community',
            'leaderboards' => false,
        ]);
    }
}
