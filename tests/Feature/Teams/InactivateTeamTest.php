<?php

namespace Tests\Feature\Teams;

use App\Models\Teams\Team;
use App\Models\User\User;
use Tests\TestCase;

class InactivateTeamTest extends TestCase
{
    public function routeDataProvider(): array
    {
        return [
            ['guard' => 'web', 'route' => 'teams/inactivate'],
            ['guard' => 'api', 'route' => 'api/teams/inactivate'],
        ];
    }

    /**
     * @dataProvider routeDataProvider
     */
    public function test_a_user_can_inactivate_their_active_team($guard, $route)
    {
        // User joins a team -------------------------
        /** @var Team $team */
        $team = Team::factory()->create();
        /** @var User $user */
        $user = User::factory()->create(['active_team' => $team->id]);

        // User inactivates their active team ------------------------
        $response = $this->actingAs($user, $guard)->postJson($route);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertNull($user->fresh()->active_team);
    }
}
