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
            ['total_litter' => 1, 'members' => 5, 'total_images' => 10],
            ['total_litter' => 2, 'members' => 3, 'total_images' => 20],
            ['total_litter' => 3, 'members' => 8, 'total_images' => 30],
        )->create();

        $result = $this
            ->actingAs($user)
            ->getJson('/api/teams/leaderboard')
            ->assertOk()
            ->assertJsonCount(3)
            ->assertJson(function (AssertableJson $json) {
                $json->has('0.name');
                $json->has('0.type_name');
                $json->has('0.total_members');
                $json->has('0.total_tags');
                $json->has('0.total_images');
                $json->has('0.created_at');
                $json->missing('0.total_litter');
            })
            ->json();

        $this->assertEquals([3, 2, 1], array_column($result, 'total_tags'));
        $this->assertEquals([8, 3, 5], array_column($result, 'total_members'));
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
            ->actingAs($user)
            ->getJson('/api/teams/leaderboard')
            ->assertOk()
            ->assertJsonCount(2)
            ->json();

        $this->assertEquals([3, 1], array_column($result, 'total_tags'));
    }
}
