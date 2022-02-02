<?php

namespace Tests\Feature\Api\Teams;

use App\Models\Teams\Team;
use App\Models\User\User;
use Tests\TestCase;

class SetActiveTeamTest extends TestCase
{

    public function test_a_user_can_set_a_team_as_their_active_team()
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var Team $team */
        $team = Team::factory()->create();
        $user->teams()->attach($team);
        $this->assertNull($user->active_team);

        $response = $this->actingAs($user, 'api')->postJson('/api/teams/active', [
            'team_id' => $team->id,
        ]);

        $response->assertOk();
        $response->assertJsonFragment(['success' => true]);
        $this->assertEquals($team->id, $user->fresh()->active_team);
    }

    public function test_a_user_can_only_set_an_active_team_if_they_are_a_member()
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var Team $team */
        $team = Team::factory()->create();
        $this->assertNull($user->active_team);

        $response = $this->actingAs($user, 'api')->postJson('/api/teams/active', [
            'team_id' => $team->id,
        ]);

        $response->assertOk();
        $response->assertJsonFragment(['success' => false, 'message' => 'not-a-member']);
        $this->assertNull($user->fresh()->active_team);
    }

    public function test_a_user_can_only_set_an_active_team_if_the_team_exists()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->assertNull($user->active_team);

        $response = $this->actingAs($user, 'api')->postJson('/api/teams/active', [
            'team_id' => 0,
        ]);

        $response->assertOk();
        $response->assertJsonFragment(['success' => false]);
        $this->assertNull($user->fresh()->active_team);
    }
}
