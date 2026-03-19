<?php

namespace Tests\Feature\Teams;

use App\Models\Teams\Team;
use App\Models\Teams\TeamType;
use App\Models\Users\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JoinTeamTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;
    private User $leader;

    protected function setUp(): void
    {
        parent::setUp();

        $type = TeamType::create(['team' => 'community', 'price' => 0, 'description' => 'Community']);

        $this->leader = User::factory()->create();

        $this->team = Team::create([
            'name' => 'Test Team',
            'identifier' => 'TestTeam2026',
            'type_id' => $type->id,
            'type_name' => 'community',
            'leader' => $this->leader->id,
            'created_by' => $this->leader->id,
        ]);

        $this->leader->teams()->attach($this->team->id);
    }

    public function test_a_user_can_join_a_team_by_identifier()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/teams/join', [
            'identifier' => 'TestTeam2026',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('team.name', 'Test Team');

        $this->assertTrue($user->fresh()->isMemberOfTeam($this->team->id));
    }

    public function test_joining_a_team_increments_member_count()
    {
        $user = User::factory()->create();

        $this->assertEquals(1, $this->team->members);

        $this->actingAs($user)->postJson('/api/teams/join', [
            'identifier' => 'TestTeam2026',
        ]);

        $this->assertEquals(2, $this->team->fresh()->members);
    }

    public function test_a_user_cannot_join_the_same_team_twice()
    {
        $user = User::factory()->create();
        $user->teams()->attach($this->team->id);

        $response = $this->actingAs($user)->postJson('/api/teams/join', [
            'identifier' => 'TestTeam2026',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('msg', 'already-joined');
    }

    public function test_join_fails_with_invalid_identifier()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/teams/join', [
                'identifier' => 'NonExistentTeam',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['identifier']);
    }

    public function test_join_validates_identifier_is_required()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/teams/join', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['identifier']);
    }

    public function test_unauthenticated_users_cannot_join_teams()
    {
        $this->postJson('/api/teams/join', [
            'identifier' => 'TestTeam2026',
        ])->assertStatus(401);
    }

    public function test_a_user_can_leave_a_team()
    {
        $user = User::factory()->create();
        $user->teams()->attach($this->team->id);
        $this->team->increment('members');

        $response = $this->actingAs($user)->postJson('/api/teams/leave', [
            'team_id' => $this->team->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertFalse($user->fresh()->isMemberOfTeam($this->team->id));
    }

    public function test_the_last_member_cannot_leave_a_team()
    {
        // Leader is the only member (members=1)
        $response = $this->actingAs($this->leader)->postJson('/api/teams/leave', [
            'team_id' => $this->team->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_a_user_can_set_active_team()
    {
        $user = User::factory()->create();
        $user->teams()->attach($this->team->id);

        $response = $this->actingAs($user)->postJson('/api/teams/active', [
            'team_id' => $this->team->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertEquals($this->team->id, $user->fresh()->active_team);
    }

    public function test_a_user_cannot_activate_a_team_they_have_not_joined()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/teams/active', [
            'team_id' => $this->team->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', false);
    }
}
