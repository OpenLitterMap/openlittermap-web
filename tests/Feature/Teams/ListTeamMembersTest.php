<?php

namespace Tests\Feature\Teams;

use Iterator;
use App\Models\Teams\Team;
use App\Models\User\User;
use Tests\TestCase;

class ListTeamMembersTest extends TestCase
{

    public function routeDataProvider(): Iterator
    {
        yield ['guard' => 'web', 'route' => 'teams/members'];
        yield ['guard' => 'api', 'route' => 'api/teams/members'];
    }

    /**
     * @dataProvider routeDataProvider
     */
    public function test_it_can_list_team_members($guard, $route)
    {
        /** @var Team $team */
        $team = Team::factory()->create();
        $users = User::factory(3)->create();
        $users->each(function (User $user) use ($team) {
            $user->teams()->attach($team);
        });
        $otherTeam = Team::factory()->create();
        $otherMember = User::factory()->create();
        $otherMember->teams()->attach($otherTeam);

        $response = $this->actingAs($users->first(), $guard)->getJson($route . '?team_id=' . $team->id);

        $response->assertOk();
        $response->assertJsonFragment(['success' => true]);

        $members = $response->json('result.data');
        $this->assertCount(3, $members);
        $this->assertEqualsCanonicalizing(
            array_column($members, 'id'),
            $users->pluck('id')->toArray()
        );
    }

    /**
     * @dataProvider routeDataProvider
     */
    public function test_team_members_have_the_correct_data($guard, $route)
    {
        /** @var Team $team */
        $team = Team::factory()->create();
        /** @var User $user */
        $user = User::factory()->create();
        $user->teams()->attach($team, [
            'show_name_leaderboards' => true,
            'show_username_leaderboards' => true
        ]);

        $response = $this->actingAs($user, $guard)->getJson($route . '?team_id=' . $team->id);

        $member = $response->json('result.data.0');
        $this->assertEquals($user->id, $member['id']);
        $this->assertEquals($user->name, $member['name']);
        $this->assertEquals($user->username, $member['username']);
        $this->assertSame($user->active_team, $member['active_team']);
        $this->assertEquals($user->updated_at->toIsoString(), $member['updated_at']);
        $this->assertEquals($user->total_photos, $member['pivot']['total_photos']);
    }

    /**
     * @dataProvider routeDataProvider
     */
    public function test_it_hides_members_names_and_usernames_depending_on_their_settings($guard, $route)
    {
        /** @var Team $team */
        $team = Team::factory()->create();
        /** @var User $user */
        $user = User::factory()->create();
        $user->teams()->attach($team, [
            'show_name_leaderboards' => false,
            'show_username_leaderboards' => false
        ]);

        $response = $this->actingAs($user, $guard)->getJson($route . '?team_id=' . $team->id);
        $member = $response->json('result.data.0');
        $this->assertEmpty($member['name']);
        $this->assertEmpty($member['username']);
    }

    /**
     * @dataProvider routeDataProvider
     */
    public function test_only_members_of_a_team_can_view_its_members($guard, $route)
    {
        /** @var Team $team */
        $team = Team::factory()->create();
        /** @var User $user */
        $user = User::factory()->create();
        $user->teams()->attach($team);
        /** @var User $nonMember */
        $nonMember = User::factory()->create();

        $response = $this->actingAs($nonMember, $guard)->getJson($route . '?team_id=' . $team->id);

        $response->assertOk();
        $response->assertJson(['success' => false, 'message' => 'not-a-member']);
    }
}
