<?php

namespace Tests\Feature\Teams;

use App\Models\Teams\Team;
use App\Models\User\User;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\HasPhotoUploads;
use Tests\TestCase;

class JoinTeamTest extends TestCase
{
    use HasPhotoUploads;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');
        Storage::fake('bbox');

        $this->setImagePath();
    }

    public function test_a_user_can_join_a_team()
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var Team $team */
        $team = Team::factory()->create(['members' => 0]);

        $this->actingAs($user);

        $this->assertSame(0, $team->fresh()->members);

        $response = $this->postJson('/teams/join', [
            'identifier' => $team->identifier,
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['success', 'team', 'activeTeam']);

        $teamPivot = $user->teams()->first();
        $this->assertNotNull($teamPivot);
        $this->assertNull($teamPivot->total_photos);
        $this->assertSame(0, $teamPivot->total_litter);
        $this->assertSame(1, $team->fresh()->members);
    }

    public function test_a_user_can_only_join_a_team_they_are_not_part_of()
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var Team $team */
        $team = Team::factory()->create(['members' => 0]);

        $this->actingAs($user);

        $this->postJson('/teams/join', [
            'identifier' => $team->identifier,
        ]);

        $response = $this->postJson('/teams/join', [
            'identifier' => $team->identifier,
        ]);

        $response->assertOk()->assertJson([
            'success' => false, 'msg' => 'already-joined'
        ]);
        $this->assertSame(1, $team->fresh()->members);
    }

    public function test_the_team_becomes_the_active_team_if_the_user_has_no_active_team()
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var Team $team */
        $team = Team::factory()->create();

        $this->actingAs($user);

        $this->postJson('/teams/join', [
            'identifier' => $team->identifier,
        ]);

        $this->assertTrue($user->fresh()->team->is($team));
    }

    public function test_the_users_active_team_does_not_change_when_they_join_another_team()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $otherTeam = Team::factory()->create();

        $this->actingAs($user);

        $this->postJson('/teams/join', [
            'identifier' => $team->identifier,
        ]);

        $this->postJson('/teams/join', [
            'identifier' => $otherTeam->identifier,
        ]);

        $this->assertTrue($user->fresh()->team->is($team));
    }

    public function test_the_team_identifier_should_be_a_valid_identifier()
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var Team $team */
        $team = Team::factory()->create();

        $this->actingAs($user);

        $this->postJson('/teams/join', ['identifier' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['identifier']);

        $this->postJson('/teams/join', ['identifier' => 'sdfgsdfgsdfg'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['identifier']);
    }

    public function test_user_contributions_are_restored_when_they_rejoin_a_team()
    {
        // User joins a team -------------------------
        /** @var User $user */
        $user = User::factory()->verified()->create();
        /** @var User $otherUser */
        $otherUser = User::factory()->create();
        /** @var Team $team */
        $team = Team::factory()->create();

        $user->active_team = $team->id;
        $user->save();
        $user->teams()->attach($team);
        $otherUser->teams()->attach($team);

        // User uploads a photo -------------
        $this->actingAs($user);

        $this->post('/submit', [
            'file' => $this->getImageAndAttributes()['file'],
        ]);

        $photo = $user->fresh()->photos->last();

        // User adds tags to the photo -------------------
        $this->post('/add-tags', [
            'photo_id' => $photo->id,
            'picked_up' => false,
            'tags' => [
                'smoking' => [
                    'butts' => 3
                ],
                'brands' => [
                    'aldi' => 2
                ]
            ]
        ]);

        $teamContributions = $user->teams()->first();

        $this->assertSame(1, $teamContributions->pivot->total_photos);
        $this->assertSame(3, $teamContributions->pivot->total_litter);

        // User leaves the team ------------------------
        $this->actingAs($user);

        $this->postJson('/teams/leave', [
            'team_id' => $team->id,
        ]);

        $this->assertNull($user->teams()->first());

        // And they join back --------------------------
        $this->postJson('/teams/join', [
            'identifier' => $team->identifier,
        ]);

        // Their contributions should be restored
        $teamContributions = $user->teams()->first();

        $this->assertNotNull($teamContributions);
        $this->assertSame(1, $teamContributions->pivot->total_photos);
        $this->assertSame(3, $teamContributions->pivot->total_litter);
    }
}
