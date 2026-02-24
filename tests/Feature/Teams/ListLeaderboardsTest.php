<?php

namespace Tests\Feature\Teams;

use App\Models\Teams\Team;
use App\Models\Users\User;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class ListLeaderboardsTest extends TestCase
{
    public function test_it_can_list_the_global_teams_leaderboards()
    {
        /** @var User $user */
        $user = User::factory()->create();

        Team::factory(3)->sequence(
            ['total_litter' => 1],
            ['total_litter' => 2],
            ['total_litter' => 3],
        )->create();

        $result = $this
            ->actingAs($user, 'api')
            ->getJson('/api/teams/leaderboard')
            ->assertOk()
            ->assertJsonCount(3)
            ->assertJson(function (AssertableJson $json) {
                $json->has('0.name');
                $json->has('0.total_litter');
                $json->has('0.total_images');
                $json->has('0.created_at');
            })
            ->json();

        $this->assertEquals([3, 2, 1], array_column($result, 'total_litter'));
    }

    public function test_it_does_not_include_teams_that_dont_want_to_be_in_leaderboards()
    {
        /** @var User $user */
        $user = User::factory()->create();

        Team::factory(3)->sequence(
            ['total_litter' => 1],
            ['total_litter' => 2, 'leaderboards' => false],
            ['total_litter' => 3],
        )->create();

        $result = $this
            ->actingAs($user, 'api')
            ->getJson('/api/teams/leaderboard')
            ->assertOk()
            ->assertJsonCount(2)
            ->json();

        $this->assertEquals([3, 1], array_column($result, 'total_litter'));
    }
}
