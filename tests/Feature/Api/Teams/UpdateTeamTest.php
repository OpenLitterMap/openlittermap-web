<?php

namespace Tests\Feature\Api\Teams;

use App\Models\Teams\Team;
use App\Models\User\User;
use Tests\TestCase;

class UpdateTeamTest extends TestCase
{
    public function test_a_team_leader_can_update_a_team()
    {
        /** @var User $leader */
        $leader = User::factory()->create();
        /** @var Team $team */
        $team = Team::factory()->create([
            'leader' => $leader->id
        ]);

        $leader->teams()->attach($team);

        $newTeamName = 'New team name';
        $newTeamIdentifier = 'New identifier';

        $this->actingAs($leader, 'api');

        $response = $this->patchJson("/api/teams/update/{$team->id}", [
            'name' => $newTeamName,
            'identifier' => $newTeamIdentifier
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure(['team']);

        $team->refresh();

        $this->assertEquals($newTeamName, $team->name);
        $this->assertEquals($newTeamIdentifier, $team->identifier);
    }

    public function test_team_members_or_other_users_can_not_update_a_team()
    {
        /** @var User $leader */
        $leader = User::factory()->create();
        /** @var User $member */
        $member = User::factory()->create();
        /** @var Team $team */
        $team = Team::factory()->create([
            'leader' => $leader->id
        ]);
        $leader->teams()->attach($team);
        $member->teams()->attach($team);
        $newTeamName = 'New team name';
        $newTeamIdentifier = 'New identifier';

        // Random users can't update a team
        $this->actingAs(User::factory()->create(), 'api');

        $response = $this->patchJson("/api/teams/update/{$team->id}", [
            'name' => $newTeamName,
            'identifier' => $newTeamIdentifier
        ]);

        $response->assertJsonFragment(['success' => false, 'message' => 'member-not-allowed']);

        // Members cannot update a team
        $this->actingAs($member);

        $response = $this->patchJson("/api/teams/update/{$team->id}", [
            'name' => $newTeamName,
            'identifier' => $newTeamIdentifier
        ]);

        $response->assertJsonFragment(['success' => false, 'message' => 'member-not-allowed']);
    }

    public function test_fields_are_validated()
    {
        /** @var User $leader */
        $leader = User::factory()->create();
        /** @var Team $team */
        $team = Team::factory()->create([
            'leader' => $leader->id
        ]);

        $leader->teams()->attach($team);

        $this->actingAs($leader, 'api');

        // Empty input
        $response = $this->patchJson("/api/teams/update/{$team->id}", [
            'name' => '',
            'identifier' => ''
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'identifier']);

        // Short input
        $response = $this->patchJson("/api/teams/update/{$team->id}", [
            'name' => 'aa',
            'identifier' => 'aa'
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'identifier']);

        // Long input
        $response = $this->patchJson("/api/teams/update/{$team->id}", [
            'name' => implode('', range(1, 101)),
            'identifier' => implode('', range(1, 16))
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'identifier']);

        // Non-unique values
        Team::factory()->create(['name' => 'name', 'identifier' => 'identifier']);

        $response = $this->patchJson("/api/teams/update/{$team->id}", [
            'name' => 'name',
            'identifier' => 'identifier'
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'identifier']);
    }
}
