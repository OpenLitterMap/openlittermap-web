<?php

namespace Tests\Feature\Teams;

use App\Models\Teams\Team;
use App\Models\Users\User;
use Tests\TestCase;

class ListTeamMembersTest extends TestCase
{
    public function test_it_can_list_team_members()
    {
        $team = Team::factory()->create();
        $users = User::factory(3)->create();
        $users->each(function (User $user) use ($team) {
            $user->teams()->attach($team);
        });
        $otherTeam = Team::factory()->create();
        $otherMember = User::factory()->create();
        $otherMember->teams()->attach($otherTeam);

        $response = $this->actingAs($users->first())->getJson('api/teams/members?team_id=' . $team->id);

        $response->assertOk();
        $response->assertJsonFragment(['success' => true]);
        $members = $response->json('result.data');
        $this->assertCount(3, $members);
        $this->assertEqualsCanonicalizing(
            array_column($members, 'id'),
            $users->pluck('id')->toArray()
        );
    }

    public function test_team_members_have_the_correct_data()
    {
        $team = Team::factory()->create();
        $user = User::factory()->create();
        $user->teams()->attach($team, [
            'show_name_leaderboards' => true,
            'show_username_leaderboards' => true
        ]);

        $response = $this->actingAs($user)->getJson('api/teams/members?team_id=' . $team->id);

        $member = $response->json('result.data.0');
        $this->assertEquals($user->id, $member['id']);
        $this->assertEquals($user->name, $member['name']);
        $this->assertEquals($user->username, $member['username']);
        $this->assertEquals($user->active_team, $member['active_team']);
    }

    public function test_members_response_includes_expected_fields()
    {
        $team = Team::factory()->create();
        $user = User::factory()->create();
        $user->teams()->attach($team, [
            'show_name_leaderboards' => true,
            'show_username_leaderboards' => true
        ]);

        $response = $this->actingAs($user)->getJson('api/teams/members?team_id=' . $team->id);
        $member = $response->json('result.data.0');
        $this->assertArrayHasKey('id', $member);
        $this->assertArrayHasKey('name', $member);
        $this->assertArrayHasKey('username', $member);
        $this->assertArrayHasKey('pivot', $member);
    }

    public function test_name_is_hidden_when_show_name_leaderboards_is_false()
    {
        $team = Team::factory()->create();
        $leader = User::factory()->create();
        $leader->teams()->attach($team, [
            'show_name_leaderboards' => true,
            'show_username_leaderboards' => true,
        ]);
        $privateMember = User::factory()->create(['name' => 'Secret Person']);
        $privateMember->teams()->attach($team, [
            'show_name_leaderboards' => false,
            'show_username_leaderboards' => false,
        ]);

        $response = $this->actingAs($leader)->getJson('api/teams/members?team_id=' . $team->id);

        $response->assertOk();
        $members = collect($response->json('result.data'));
        $private = $members->firstWhere('id', $privateMember->id);

        $this->assertNotNull($private, 'Private member should appear in member list');
        $this->assertEmpty($private['name'], 'Name should be empty when show_name_leaderboards is false');
        $this->assertEmpty($private['username'], 'Username should be empty when show_username_leaderboards is false');
    }

    public function test_only_members_of_a_team_can_view_its_members()
    {
        $team = Team::factory()->create();
        $user = User::factory()->create();
        $user->teams()->attach($team);
        $nonMember = User::factory()->create();

        $response = $this->actingAs($nonMember)->getJson('api/teams/members?team_id=' . $team->id);

        $response->assertOk();
        $response->assertJson(['success' => false, 'message' => 'not-a-member']);
    }
}
