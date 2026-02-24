<?php

namespace Tests\Feature\Teams;

use App\Models\Teams\Team;
use App\Models\Users\User;
use Tests\TestCase;

class InactivateTeamTest extends TestCase
{
    public function test_a_user_can_inactivate_their_active_team()
    {
        /** @var Team $team */
        $team = Team::factory()->create();
        /** @var User $user */
        $user = User::factory()->create(['active_team' => $team->id]);

        $response = $this->actingAs($user, 'api')->postJson('api/teams/inactivate');

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertNull($user->fresh()->active_team);
    }
}
