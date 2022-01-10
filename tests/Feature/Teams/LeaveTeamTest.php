<?php

namespace Tests\Feature\Teams;

use App\Models\Teams\Team;
use App\Models\User\User;
use Tests\TestCase;

class LeaveTeamTest extends TestCase
{

    public function test_a_user_can_leave_a_team()
    {
        // User joins a team -------------------------
        /** @var User $user */
        $user = User::factory()->create();
        /** @var User $otherUser */
        $otherUser = User::factory()->create();
        /** @var Team $team */
        $team = Team::factory()->create();

        $user->teams()->attach($team);
        $otherUser->teams()->attach($team);
        $team->update(['members' => 2]);

        $this->assertCount(1, $user->teams);
        $this->assertCount(2, $team->fresh()->users);

        // User leaves a team ------------------------
        $this->actingAs($user);

        $response = $this->postJson('/teams/leave', [
            'team_id' => $team->id,
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure(['success', 'team', 'activeTeam']);

        $user->refresh();
        $team->refresh();

        $this->assertEmpty($user->teams);
        $this->assertCount(1, $team->users);
        $this->assertEquals(1, $team->members);
    }

    public function test_a_user_can_only_leave_a_team_they_are_part_of()
    {
        $user = User::factory()->create();
        $otherUserJoinsTeam = User::factory()->create();
        $team = Team::factory()->create();

        $otherUserJoinsTeam->teams()->attach($team);
        $otherUserJoinsTeam->save();

        $this->assertCount(0, $user->teams);
        $this->assertCount(1, $otherUserJoinsTeam->teams);
        $this->assertCount(1, $team->fresh()->users);

        $this->actingAs($user);

        // Non-existing team -------------------------
        $response = $this->postJson('/teams/leave', [
            'team_id' => 0,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('team_id');

        // Leaving a team that they are not part of ---------------
        $response = $this->postJson('/teams/leave', [
            'team_id' => $team->id,
        ]);

        $response->assertForbidden();
    }

    public function test_a_user_is_assigned_their_next_team_as_active_if_they_leave_the_active_team()
    {
        // User joins a team -------------------------
        /** @var User $user */
        $user = User::factory()->create();
        /** @var User $otherUser */
        $otherUser = User::factory()->create();
        /** @var Team $activeTeam */
        $activeTeam = Team::factory()->create();
        /** @var Team $otherTeam */
        $otherTeam = Team::factory()->create();

        $user->teams()->attach($activeTeam);
        $user->teams()->attach($otherTeam);
        $otherUser->teams()->attach($activeTeam);

        $user->active_team = $activeTeam->id;
        $user->save();

        $this->assertTrue($user->team->is($activeTeam));

        // User leaves their active team ------------------------
        $this->actingAs($user);

        $response = $this->postJson('/teams/leave', [
            'team_id' => $activeTeam->id,
        ]);

        $response->assertOk();

        $this->assertEquals($otherTeam->id, $response->json()['activeTeam']['id']);

        $this->assertTrue($user->fresh()->team->is($otherTeam));
    }

    public function test_a_new_leader_is_assigned_to_the_team_when_the_leader_leaves()
    {
        $leader = User::factory()->create();
        $otherUserInTeam = User::factory()->create();
        $team = Team::factory()->create([
            'leader' => $leader->id
        ]);

        $leader->teams()->attach($team);
        $otherUserInTeam->teams()->attach($team);

        $this->assertTrue($leader->is(User::find($team->leader)));

        $this->actingAs($leader);

        // Non-existing team -------------------------
        $response = $this->postJson('/teams/leave', [
            'team_id' => $team->id,
        ]);

        $response->assertOk();

        $this->assertTrue($otherUserInTeam->is(User::find($team->fresh()->leader)));
    }

    public function test_a_user_can_not_leave_a_team_if_they_are_the_only_member()
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var Team $team */
        $team = Team::factory()->create();

        $user->teams()->attach($team);

        $this->assertCount(1, $user->teams);
        $this->assertCount(1, $team->fresh()->users);

        // User leaves a team ------------------------
        $this->actingAs($user);

        $response = $this->postJson('/teams/leave', [
            'team_id' => $team->id,
        ]);

        $response->assertForbidden();

        $this->assertCount(1, $user->teams);
        $this->assertCount(1, $team->fresh()->users);
    }
}
