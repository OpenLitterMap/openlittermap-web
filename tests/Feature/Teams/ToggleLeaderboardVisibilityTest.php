<?php

namespace Tests\Feature\Teams;

use App\Models\Teams\Team;
use App\Models\Users\User;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('deprecated')]
class ToggleLeaderboardVisibilityTest extends TestCase
{
    public static function routeDataProvider(): array
    {
        return [
            ['/teams/leaderboard/visibility', 'web'],
            ['/api/teams/leaderboard/visibility', 'api'],
        ];
    }

    /**
     * @dataProvider routeDataProvider
     */
    public function test_it_can_toggle_the_visibility_of_teams_leaderboards($route, $guard)
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var Team $team */
        $team = Team::factory()->create(['leader' => $user->id, 'leaderboards' => false]);

        $response = $this->actingAs($user, $guard)->postJson($route, ['team_id' => $team->id]);

        $response->assertOk();
        $response->assertJson(['success' => true, 'visible' => true]);
        $this->assertEquals(1, $team->fresh()->leaderboards);

        $response = $this->actingAs($user, $guard)->postJson($route, ['team_id' => $team->id]);

        $response->assertOk();
        $response->assertJson(['success' => true, 'visible' => false]);
        $this->assertEquals(0, $team->fresh()->leaderboards);
    }

    /**
     * @dataProvider routeDataProvider
     */
    public function test_only_the_team_leader_can_toggle_the_visibility_of_teams_leaderboards($route, $guard)
    {
        $leader = User::factory()->create();
        /** @var User $member */
        $member = User::factory()->create();
        /** @var Team $team */
        $team = Team::factory()->create(['leader' => $leader->id, 'leaderboards' => false]);
        $team->users()->attach($leader);
        $team->users()->attach($member);

        $response = $this->actingAs($member, $guard)->postJson($route, ['team_id' => $team->id]);

        $response->assertOk();
        $response->assertJson(['success' => false, 'message' => 'member-not-allowed']);
        $this->assertEquals(0, $team->fresh()->leaderboards);
    }
}
