<?php

namespace Tests\Feature\Teams;

use App\Models\Teams\Team;
use App\Models\User\User;
use Tests\TestCase;

class InactivateTeamTest extends TestCase
{
    public function test_a_user_can_inactivate_their_active_team()
    {
        // User joins a team -------------------------
        /** @var Team $team */
        $team = Team::factory()->create();
        /** @var User $user */
        $user = User::factory()->create([
            'active_team' => $team->id
        ]);

        // User inactivates their active team ------------------------
        $this->actingAs($user);

        $response = $this->postJson('/teams/inactivate');

        $response
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertNull($user->fresh()->active_team);
    }
}
