<?php

namespace Tests\Feature\Teams;

use App\Models\Teams\Team;
use App\Models\Users\User;
use Tests\TestCase;

class ToggleLeaderboardVisibilityTest extends TestCase
{
    public function test_it_can_toggle_the_visibility_of_teams_leaderboards()
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var Team $team */
        $team = Team::factory()->create(['leader' => $user->id, 'leaderboards' => false]);

        $response = $this->actingAs($user, 'api')->postJson('/api/teams/leaderboard/visibility', ['team_id' => $team->id]);

        $response->assertOk();
        $response->assertJson(['success' => true, 'visible' => true]);
        $this->assertEquals(1, $team->fresh()->leaderboards);

        $response = $this->actingAs($user, 'api')->postJson('/api/teams/leaderboard/visibility', ['team_id' => $team->id]);

        $response->assertOk();
        $response->assertJson(['success' => true, 'visible' => false]);
        $this->assertEquals(0, $team->fresh()->leaderboards);
    }

    public function test_only_the_team_leader_can_toggle_the_visibility_of_teams_leaderboards()
    {
        $leader = User::factory()->create();
        /** @var User $member */
        $member = User::factory()->create();
        /** @var Team $team */
        $team = Team::factory()->create(['leader' => $leader->id, 'leaderboards' => false]);
        $team->users()->attach($leader);
        $team->users()->attach($member);

        $response = $this->actingAs($member, 'api')->postJson('/api/teams/leaderboard/visibility', ['team_id' => $team->id]);

        $response->assertOk();
        $response->assertJson(['success' => false, 'message' => 'member-not-allowed']);
        $this->assertEquals(0, $team->fresh()->leaderboards);
    }
}
