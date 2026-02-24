<?php

namespace Tests\Feature\Teams;

use App\Events\TeamCreated;
use App\Models\Teams\Team;
use App\Models\Teams\TeamType;
use App\Models\Users\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class TeamsTest extends TestCase
{
    use RefreshDatabase;

    private TeamType $communityType;
    private Team $team;
    private User $leader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->communityType = TeamType::create([
            'team' => 'community', 'price' => 0, 'description' => 'Community',
        ]);

        $this->leader = User::factory()->create(['remaining_teams' => 3]);

        $this->team = Team::create([
            'name' => 'Test Team',
            'identifier' => 'TestTeam2026',
            'type_id' => $this->communityType->id,
            'type_name' => 'community',
            'leader' => $this->leader->id,
            'created_by' => $this->leader->id,
        ]);

        $this->leader->teams()->attach($this->team->id);
        $this->leader->update(['active_team' => $this->team->id]);
    }

    // ── Events ──

    public function test_team_created_event_is_fired()
    {
        Event::fake([TeamCreated::class]);

        $user = User::factory()->create(['remaining_teams' => 3]);

        $this->actingAs($user, 'api')->postJson('/api/teams/create', [
            'name' => 'Event Team',
            'identifier' => 'EventTeam1',
            'teamType' => $this->communityType->id,
        ]);

        Event::assertDispatched(TeamCreated::class, function ($event) {
            return $event->team->name === 'Event Team';
        });
    }

    // ── Inactivate Team ──

    public function test_user_can_inactivate_their_active_team()
    {
        $this->assertEquals($this->team->id, $this->leader->active_team);

        $response = $this->actingAs($this->leader, 'api')
            ->postJson('/api/teams/inactivate');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNull($this->leader->fresh()->active_team);
    }

    // ── Leave Clears Active Team ──

    public function test_leaving_active_team_clears_active_team()
    {
        $member = User::factory()->create();
        $member->teams()->attach($this->team->id);
        $member->update(['active_team' => $this->team->id]);
        $this->team->increment('members');

        $this->actingAs($member, 'api')->postJson('/api/teams/leave', [
            'team_id' => $this->team->id,
        ]);

        // active_team should be cleared or set to another team
        $this->assertNotEquals($this->team->id, $member->fresh()->active_team);
    }

    public function test_user_cannot_leave_team_they_are_not_in()
    {
        $outsider = User::factory()->create();

        $this->actingAs($outsider, 'api')
            ->postJson('/api/teams/leave', ['team_id' => $this->team->id])
            ->assertStatus(403);
    }

    // ── Update Team ──

    public function test_leader_can_update_team_name_and_identifier()
    {
        $response = $this->actingAs($this->leader, 'api')
            ->patchJson('/api/teams/update/' . $this->team->id, [
                'name' => 'Updated Name',
                'identifier' => 'UpdatedID',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('team.name', 'Updated Name');

        $this->assertDatabaseHas('teams', [
            'id' => $this->team->id,
            'name' => 'Updated Name',
            'identifier' => 'UpdatedID',
        ]);
    }

    public function test_non_leader_cannot_update_team()
    {
        $member = User::factory()->create();
        $member->teams()->attach($this->team->id);

        $this->actingAs($member, 'api')
            ->patchJson('/api/teams/update/' . $this->team->id, [
                'name' => 'Hijacked Name',
                'identifier' => 'Hijacked1',
            ])
            ->assertStatus(403);

        // Name should not have changed
        $this->assertEquals('Test Team', $this->team->fresh()->name);
    }

    public function test_update_validates_unique_name()
    {
        Team::create([
            'name' => 'Other Team',
            'identifier' => 'Other1',
            'type_id' => $this->communityType->id,
            'type_name' => 'community',
            'leader' => $this->leader->id,
            'created_by' => $this->leader->id,
        ]);

        $this->actingAs($this->leader, 'api')
            ->patchJson('/api/teams/update/' . $this->team->id, [
                'name' => 'Other Team',
                'identifier' => 'Other1',
            ])
            ->assertStatus(422);
    }

    // ── Team Types ──

    public function test_authenticated_user_can_fetch_team_types()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')
            ->getJson('/api/teams/types');

        $response->assertOk();

        // community from setUp + school from migration
        $types = collect($response->json('types'));
        $this->assertCount(2, $types);
        $this->assertContains('community', $types->pluck('team'));
    }

    // ── Joined Teams ──

    public function test_user_can_fetch_their_joined_teams()
    {
        $response = $this->actingAs($this->leader, 'api')
            ->getJson('/api/teams/joined');

        $response->assertOk();
        $this->assertCount(1, $response->json());
        $this->assertEquals('Test Team', $response->json()[0]['name']);
    }

    public function test_user_with_no_teams_gets_empty_array()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')
            ->getJson('/api/teams/joined');

        $response->assertOk();
        $this->assertCount(0, $response->json());
    }

    // ── Members (non-safeguarding) ──

    public function test_member_can_view_team_members()
    {
        $response = $this->actingAs($this->leader, 'api')
            ->getJson('/api/teams/members?' . http_build_query([
                'team_id' => $this->team->id,
            ]));

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertGreaterThanOrEqual(1, count($response->json('result.data')));
    }

    public function test_non_member_cannot_view_team_members()
    {
        $outsider = User::factory()->create();

        $response = $this->actingAs($outsider, 'api')
            ->getJson('/api/teams/members?' . http_build_query([
                'team_id' => $this->team->id,
            ]));

        $response->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'not-a-member');
    }

    // ── Dashboard Stats ──

    public function test_member_can_fetch_dashboard_data()
    {
        $response = $this->actingAs($this->leader, 'api')
            ->getJson('/api/teams/data?' . http_build_query([
                'team_id' => $this->team->id,
                'period' => 'all',
            ]));

        $response->assertOk()
            ->assertJsonStructure([
                'photos_count',
                'litter_count',
                'members_count',
            ]);
    }

    public function test_dashboard_filters_by_period()
    {
        $response = $this->actingAs($this->leader, 'api')
            ->getJson('/api/teams/data?' . http_build_query([
                'team_id' => $this->team->id,
                'period' => 'today',
            ]));

        $response->assertOk();

        // With no photos, all counts should be 0
        $this->assertEquals(0, $response->json('photos_count'));
        $this->assertEquals(0, $response->json('litter_count'));
    }

    public function test_non_member_cannot_access_dashboard_of_specific_team()
    {
        $outsider = User::factory()->create();

        $response = $this->actingAs($outsider, 'api')
            ->getJson('/api/teams/data?' . http_build_query([
                'team_id' => $this->team->id,
                'period' => 'all',
            ]));

        // Should return empty/zero data since outsider has no teams
        $response->assertOk();
        $this->assertEquals(0, $response->json('photos_count'));
    }

    // ── Privacy Settings ──

    public function test_member_can_update_privacy_settings_for_one_team()
    {
        $response = $this->actingAs($this->leader, 'api')
            ->postJson('/api/teams/settings', [
                'team_id' => $this->team->id,
                'all' => false,
                'settings' => [
                    'show_name_maps' => true,
                    'show_username_maps' => false,
                    'show_name_leaderboards' => true,
                    'show_username_leaderboards' => false,
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $pivot = $this->leader->teams()->where('team_id', $this->team->id)->first()->pivot;
        $this->assertTrue((bool) $pivot->show_name_maps);
        $this->assertFalse((bool) $pivot->show_username_maps);
        $this->assertTrue((bool) $pivot->show_name_leaderboards);
        $this->assertFalse((bool) $pivot->show_username_leaderboards);
    }

    public function test_member_can_apply_privacy_settings_to_all_teams()
    {
        // Join a second team
        $team2 = Team::create([
            'name' => 'Second Team',
            'identifier' => 'Second1',
            'type_id' => $this->communityType->id,
            'type_name' => 'community',
            'leader' => $this->leader->id,
            'created_by' => $this->leader->id,
        ]);
        $this->leader->teams()->attach($team2->id);

        $this->actingAs($this->leader, 'api')
            ->postJson('/api/teams/settings', [
                'team_id' => $this->team->id,
                'all' => true,
                'settings' => [
                    'show_name_maps' => true,
                    'show_username_maps' => true,
                    'show_name_leaderboards' => true,
                    'show_username_leaderboards' => true,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        // Both teams should have the settings
        foreach ([$this->team->id, $team2->id] as $teamId) {
            $pivot = $this->leader->teams()->where('team_id', $teamId)->first()->pivot;
            $this->assertTrue((bool) $pivot->show_name_maps);
            $this->assertTrue((bool) $pivot->show_username_maps);
        }
    }
}
