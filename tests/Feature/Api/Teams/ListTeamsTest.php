<?php

namespace Tests\Feature\Api\Teams;

use App\Models\Teams\Team;
use App\Models\Teams\TeamType;
use App\Models\User\User;
use Tests\TestCase;

class ListTeamsTest extends TestCase
{

    public function test_it_can_list_a_users_teams()
    {
        // User joins a team -------------------------
        /** @var User $user */
        $user = User::factory()->create();
        /** @var Team $team */
        $team = Team::factory()->create();
        $otherTeam = Team::factory()->create();

        $user->teams()->attach($team);
        $team->update(['members' => 2]);

        // User lists his teams ------------------------
        $this->actingAs($user, 'api');

        $this->getJson('/api/teams/list')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['id' => $team->id]);
    }

    public function test_it_can_list_all_available_team_types()
    {
        $teamType = TeamType::factory()->create();

        $this->getJson('/api/teams/types')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['id' => $teamType->id]);
    }
}
