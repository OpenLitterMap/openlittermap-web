<?php

namespace Tests\Feature\Api\Teams;

use App\Models\Teams\Team;
use App\Models\Teams\TeamType;
use App\Models\Users\User;
use Tests\TestCase;

class ListTeamsTest extends TestCase
{

    public function test_it_can_list_a_users_teams()
    {
        // User joins a team -------------------------
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $otherTeam = Team::factory()->create();
        $user->teams()->attach($team);
        $team->update(['members' => 2, 'total_litter' => 15, 'total_images' => 5]);

        // User lists his teams ------------------------
        $response = $this->actingAs($user)
            ->getJson('/api/teams/list')
            ->assertOk()
            ->json('teams');

        $this->assertCount(1, $response);
        $this->assertEquals($team->id, $response[0]['id']);
        $this->assertEquals($team->name, $response[0]['name']);
        $this->assertEquals('community', $response[0]['type_name']);
        $this->assertEquals(2, $response[0]['total_members']);
        $this->assertEquals(15, $response[0]['total_tags']);
        $this->assertEquals(5, $response[0]['total_images']);
        $this->assertArrayNotHasKey('total_litter', $response[0]);
    }

    public function test_it_can_list_all_available_team_types()
    {
        $teamType = TeamType::factory()->create();

        $response = $this->getJson('/api/teams/types')
            ->assertOk()
            ->json('types');

        $this->assertCount(2, $response);
        $this->assertEquals($teamType->id, $response[0]['id']);
    }
}
