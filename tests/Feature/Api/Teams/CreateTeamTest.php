<?php

namespace Tests\Feature\Api\Teams;

use App\Events\TeamCreated;
use App\Models\Teams\Team;
use App\Models\Teams\TeamType;
use App\Models\User\User;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CreateTeamTest extends TestCase
{
    /** @var User */
    private $user;

    /** @var int */
    private $teamTypeId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->teamTypeId = TeamType::factory()->create()->id;

        $this->actingAs($this->user, 'api');
    }

    public function test_a_user_can_create_a_team()
    {
        $response = $this->postJson('/api/teams/create', [
            'name' => 'team name',
            'identifier' => 'test-id',
            'team_type' => $this->teamTypeId
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['team']);

        $team = Team::whereIdentifier('test-id')->first();
        $this->assertInstanceOf(Team::class, $team);
        $this->assertSame(1, $team->members);
        $this->assertSame('team name', $team->name);
        $this->assertSame('test-id', $team->identifier);
        $this->assertSame($this->teamTypeId, $team->type_id);
        $this->assertEquals($this->user->id, $team->leader);
        $this->assertEquals($this->user->id, $team->created_by);
    }

    public function test_a_user_can_not_create_more_teams_than_allowed()
    {
        $this->postJson('/api/teams/create', [
            'name' => 'team name',
            'identifier' => 'test-id',
            'team_type' => $this->teamTypeId
        ]);
        $response = $this->postJson('/api/teams/create', [
            'name' => 'team name 2',
            'identifier' => 'test-id-2',
            'team_type' => $this->teamTypeId
        ]);

        $response->assertJsonFragment(['success' => false, 'message' => 'max-teams-created']);

        $this->assertDatabaseCount('teams', 1);
    }

    public function test_it_fires_team_created_event()
    {
        Event::fake(TeamCreated::class);

        $this->postJson('/api/teams/create', [
            'name' => 'team name',
            'identifier' => 'test-id',
            'team_type' => $this->teamTypeId
        ]);

        Event::assertDispatched(
            TeamCreated::class,
            function (TeamCreated $event) {
                $this->assertSame('team name', $event->teamName);

                return true;
            }
        );
    }

    public function test_user_team_info_is_updated()
    {
        $this->postJson('/api/teams/create', [
            'name' => 'team name',
            'identifier' => 'test-id',
            'team_type' => $this->teamTypeId
        ]);

        $team = Team::whereIdentifier('test-id')->first();
        $this->assertEquals($team->id, $this->user->active_team);
        $this->assertSame(0, $this->user->remaining_teams);

        $teamPivot = $this->user->teams()->first();
        $this->assertNotNull($teamPivot);
        $this->assertNull($teamPivot->total_photos);
        $this->assertSame(0, $teamPivot->total_litter);
    }
}
