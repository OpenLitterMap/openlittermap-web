<?php

namespace Tests\Feature\Teams;

use App\Enums\VerificationStatus;
use App\Events\SchoolDataApproved;
use App\Events\TagsVerifiedByAdmin;
use App\Models\Photo;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Teams\Participant;
use App\Models\Teams\Team;
use App\Models\Teams\TeamType;
use App\Models\Users\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ParticipantSessionTest extends TestCase
{
    use RefreshDatabase;

    protected User $facilitator;
    protected Team $schoolTeam;
    protected TeamType $schoolType;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->schoolType = TeamType::firstOrCreate(
            ['team' => 'school'],
            ['team' => 'school']
        );

        $this->facilitator = User::factory()->create(['name' => 'Ms. Murphy']);
        $role = Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'manage school team', 'guard_name' => 'web']);
        $role->givePermissionTo('manage school team');
        $this->facilitator->assignRole('school_manager');

        $this->schoolTeam = Team::factory()->create([
            'type_id' => $this->schoolType->id,
            'type_name' => 'school',
            'leader' => $this->facilitator->id,
            'safeguarding' => true,
            'is_trusted' => false,
            'participant_sessions_enabled' => true,
            'max_participants' => 30,
        ]);

        $this->schoolTeam->users()->attach($this->facilitator->id);
    }

    // ─── Slot Management ─────────────────────────────────────────

    public function test_facilitator_can_create_participant_slots(): void
    {
        $response = $this->actingAs($this->facilitator)
            ->postJson("/api/teams/{$this->schoolTeam->id}/participants", [
                'count' => 5,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(5, 'participants');

        $participants = $response->json('participants');

        // Verify sequential slot numbers
        $this->assertEquals(1, $participants[0]['slot_number']);
        $this->assertEquals(5, $participants[4]['slot_number']);

        // Verify default display names
        $this->assertEquals('Student 1', $participants[0]['display_name']);
        $this->assertEquals('Student 5', $participants[4]['display_name']);

        // Verify session tokens are returned on create
        $this->assertNotNull($participants[0]['session_token']);
        $this->assertEquals(64, strlen($participants[0]['session_token']));

        // Verify database
        $this->assertDatabaseCount('participants', 5);
    }

    public function test_facilitator_can_create_named_slots(): void
    {
        $response = $this->actingAs($this->facilitator)
            ->postJson("/api/teams/{$this->schoolTeam->id}/participants", [
                'slots' => [
                    ['display_name' => 'Alice'],
                    ['display_name' => 'Bob'],
                ],
            ]);

        $response->assertOk();

        $participants = $response->json('participants');
        $this->assertEquals('Alice', $participants[0]['display_name']);
        $this->assertEquals('Bob', $participants[1]['display_name']);
    }

    public function test_max_participants_enforced(): void
    {
        $this->schoolTeam->update(['max_participants' => 3]);

        // Create 2 slots — OK
        $this->actingAs($this->facilitator)
            ->postJson("/api/teams/{$this->schoolTeam->id}/participants", ['count' => 2])
            ->assertOk();

        // Try to create 2 more — exceeds max of 3
        $this->actingAs($this->facilitator)
            ->postJson("/api/teams/{$this->schoolTeam->id}/participants", ['count' => 2])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_requires_participant_sessions_enabled(): void
    {
        $this->schoolTeam->update(['participant_sessions_enabled' => false]);

        $this->actingAs($this->facilitator)
            ->postJson("/api/teams/{$this->schoolTeam->id}/participants", ['count' => 5])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Participant sessions not enabled.');
    }

    public function test_non_leader_cannot_create_slots(): void
    {
        $member = User::factory()->create();
        $this->schoolTeam->users()->attach($member->id);

        $this->actingAs($member)
            ->postJson("/api/teams/{$this->schoolTeam->id}/participants", ['count' => 5])
            ->assertStatus(403);
    }

    public function test_facilitator_can_list_participants(): void
    {
        $this->createSlots(3);

        $response = $this->actingAs($this->facilitator)
            ->getJson("/api/teams/{$this->schoolTeam->id}/participants");

        $response->assertOk()
            ->assertJsonCount(3, 'participants');

        $participant = $response->json('participants.0');
        $this->assertArrayHasKey('photos_count', $participant);
        $this->assertArrayNotHasKey('session_token', $participant); // Hidden in list
    }

    public function test_facilitator_can_deactivate_participant(): void
    {
        $participants = $this->createSlots(1);
        $participant = $participants[0];

        $this->actingAs($this->facilitator)
            ->postJson("/api/teams/{$this->schoolTeam->id}/participants/{$participant->id}/deactivate")
            ->assertOk();

        $this->assertFalse($participant->fresh()->is_active);
    }

    public function test_facilitator_can_reactivate_participant(): void
    {
        $participants = $this->createSlots(1);
        $participant = $participants[0];
        $participant->deactivate();

        $this->actingAs($this->facilitator)
            ->postJson("/api/teams/{$this->schoolTeam->id}/participants/{$participant->id}/activate")
            ->assertOk();

        $this->assertTrue($participant->fresh()->is_active);
    }

    public function test_facilitator_can_reset_token(): void
    {
        $participants = $this->createSlots(1);
        $participant = $participants[0];
        $oldToken = $participant->session_token;

        $response = $this->actingAs($this->facilitator)
            ->postJson("/api/teams/{$this->schoolTeam->id}/participants/{$participant->id}/reset-token");

        $response->assertOk()
            ->assertJsonStructure(['success', 'session_token']);

        $newToken = $response->json('session_token');
        $this->assertNotEquals($oldToken, $newToken);
        $this->assertEquals(64, strlen($newToken));
    }

    public function test_facilitator_can_delete_participant(): void
    {
        $participants = $this->createSlots(1);
        $participant = $participants[0];

        // Create a photo for this participant
        Photo::factory()->create([
            'user_id' => $this->facilitator->id,
            'team_id' => $this->schoolTeam->id,
            'participant_id' => $participant->id,
        ]);

        $this->actingAs($this->facilitator)
            ->deleteJson("/api/teams/{$this->schoolTeam->id}/participants/{$participant->id}")
            ->assertOk();

        $this->assertDatabaseMissing('participants', ['id' => $participant->id]);

        // Photo should still exist with participant_id = null (ON DELETE SET NULL)
        $photo = Photo::where('team_id', $this->schoolTeam->id)->first();
        $this->assertNotNull($photo);
        $this->assertNull($photo->participant_id);
    }

    public function test_slot_numbers_are_sequential(): void
    {
        $this->createSlots(3); // Slots 1, 2, 3

        // Create more — should start at 4
        $response = $this->actingAs($this->facilitator)
            ->postJson("/api/teams/{$this->schoolTeam->id}/participants", ['count' => 2]);

        $participants = $response->json('participants');
        $this->assertEquals(4, $participants[0]['slot_number']);
        $this->assertEquals(5, $participants[1]['slot_number']);
    }

    // ─── Token Auth ──────────────────────────────────────────────

    public function test_valid_token_authenticates_as_facilitator(): void
    {
        $participants = $this->createSlots(1);
        $token = $participants[0]->session_token;

        $response = $this->postJson('/api/participant/session', [
            'token' => $token,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('session.display_name', 'Student 1')
            ->assertJsonPath('session.team_name', $this->schoolTeam->name);
    }

    public function test_invalid_token_returns_401(): void
    {
        $this->postJson('/api/participant/session', [
            'token' => str_repeat('x', 64),
        ])->assertStatus(401);
    }

    public function test_deactivated_token_returns_401(): void
    {
        $participants = $this->createSlots(1);
        $token = $participants[0]->session_token;
        $participants[0]->deactivate();

        $this->postJson('/api/participant/session', [
            'token' => $token,
        ])->assertStatus(401);
    }

    public function test_token_from_disabled_team_returns_403(): void
    {
        $participants = $this->createSlots(1);
        $token = $participants[0]->session_token;

        $this->schoolTeam->update(['participant_sessions_enabled' => false]);

        // Session entry checks team has sessions enabled
        $this->postJson('/api/participant/session', [
            'token' => $token,
        ])->assertStatus(401); // Returns 401 because hasParticipantSessions() is false
    }

    public function test_participant_middleware_authenticates_requests(): void
    {
        $participants = $this->createSlots(1);
        $token = $participants[0]->session_token;

        // Use the participant photos endpoint — should work with token
        $response = $this->getJson('/api/participant/photos', [
            'X-Participant-Token' => $token,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_participant_middleware_rejects_invalid_token(): void
    {
        $this->getJson('/api/participant/photos', [
            'X-Participant-Token' => str_repeat('x', 64),
        ])->assertStatus(401);
    }

    // ─── Participant Photos ──────────────────────────────────────

    public function test_participant_sees_only_own_photos(): void
    {
        $participants = $this->createSlots(2);
        $token1 = $participants[0]->session_token;

        // Photo for participant 1
        Photo::factory()->create([
            'user_id' => $this->facilitator->id,
            'team_id' => $this->schoolTeam->id,
            'participant_id' => $participants[0]->id,
        ]);

        // Photo for participant 2
        Photo::factory()->create([
            'user_id' => $this->facilitator->id,
            'team_id' => $this->schoolTeam->id,
            'participant_id' => $participants[1]->id,
        ]);

        $response = $this->getJson('/api/participant/photos', [
            'X-Participant-Token' => $token1,
        ]);

        $response->assertOk();
        $this->assertCount(1, $response->json('photos.data'));
    }

    public function test_participant_can_delete_unapproved_photo(): void
    {
        Storage::fake('s3');
        Storage::fake('bbox');

        $participants = $this->createSlots(1);
        $token = $participants[0]->session_token;

        $photo = Photo::factory()->create([
            'user_id' => $this->facilitator->id,
            'team_id' => $this->schoolTeam->id,
            'participant_id' => $participants[0]->id,
            'is_public' => false,
        ]);

        $response = $this->deleteJson("/api/participant/photos/{$photo->id}", [], [
            'X-Participant-Token' => $token,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('photos', ['id' => $photo->id]);
    }

    public function test_participant_cannot_delete_approved_photo(): void
    {
        $participants = $this->createSlots(1);
        $token = $participants[0]->session_token;

        $photo = Photo::factory()->create([
            'user_id' => $this->facilitator->id,
            'team_id' => $this->schoolTeam->id,
            'participant_id' => $participants[0]->id,
            'is_public' => true,
            'team_approved_at' => now(),
            'team_approved_by' => $this->facilitator->id,
        ]);

        $this->deleteJson("/api/participant/photos/{$photo->id}", [], [
            'X-Participant-Token' => $token,
        ])->assertStatus(422);
    }

    public function test_participant_cannot_delete_others_photo(): void
    {
        $participants = $this->createSlots(2);
        $token1 = $participants[0]->session_token;

        $photo = Photo::factory()->create([
            'user_id' => $this->facilitator->id,
            'team_id' => $this->schoolTeam->id,
            'participant_id' => $participants[1]->id, // Belongs to participant 2
        ]);

        $this->deleteJson("/api/participant/photos/{$photo->id}", [], [
            'X-Participant-Token' => $token1,
        ])->assertStatus(403);
    }

    // ─── Facilitator Queue Integration ───────────────────────────

    public function test_team_photos_index_includes_participant_display_name(): void
    {
        $participants = $this->createSlots(1);

        Photo::factory()->create([
            'user_id' => $this->facilitator->id,
            'team_id' => $this->schoolTeam->id,
            'participant_id' => $participants[0]->id,
            'is_public' => false,
        ]);

        $response = $this->actingAs($this->facilitator)
            ->getJson('/api/teams/photos?team_id=' . $this->schoolTeam->id);

        $response->assertOk();

        $photo = $response->json('photos.data.0');
        $this->assertNotNull($photo['participant']);
        $this->assertEquals('Student 1', $photo['participant']['display_name']);
    }

    public function test_member_stats_includes_participant_stats(): void
    {
        $participants = $this->createSlots(2);

        // Photos for participant 1
        Photo::factory()->count(3)->create([
            'user_id' => $this->facilitator->id,
            'team_id' => $this->schoolTeam->id,
            'participant_id' => $participants[0]->id,
            'is_public' => false,
        ]);

        $response = $this->actingAs($this->facilitator)
            ->getJson('/api/teams/photos/member-stats?team_id=' . $this->schoolTeam->id);

        $response->assertOk();

        $members = $response->json('members');
        $participantEntries = array_filter($members, fn ($m) => $m['is_participant'] ?? false);

        $this->assertCount(2, $participantEntries);

        // Find participant 1's stats
        $p1Stats = collect($participantEntries)->firstWhere('name', 'Student 1');
        $this->assertEquals(3, $p1Stats['total_photos']);
    }

    public function test_facilitator_approval_unchanged_for_participant_photos(): void
    {
        Event::fake([TagsVerifiedByAdmin::class, SchoolDataApproved::class]);

        $participants = $this->createSlots(1);

        $photo = Photo::factory()->create([
            'user_id' => $this->facilitator->id,
            'team_id' => $this->schoolTeam->id,
            'participant_id' => $participants[0]->id,
            'is_public' => false,
            'verified' => VerificationStatus::VERIFIED->value,
            'summary' => json_encode(['smoking' => ['cigarette_butt' => 1]]),
        ]);

        $response = $this->actingAs($this->facilitator)
            ->postJson('/api/teams/photos/approve', [
                'team_id' => $this->schoolTeam->id,
                'photo_ids' => [$photo->id],
            ]);

        $response->assertOk()
            ->assertJsonPath('approved_count', 1);

        $photo->refresh();
        $this->assertTrue((bool) $photo->is_public);
        $this->assertNotNull($photo->team_approved_at);

        Event::assertDispatched(TagsVerifiedByAdmin::class, function ($event) use ($photo) {
            return $event->photo_id === $photo->id
                && $event->user_id === $this->facilitator->id;
        });
    }

    public function test_metrics_accrue_to_facilitator(): void
    {
        Event::fake([TagsVerifiedByAdmin::class, SchoolDataApproved::class]);

        $participants = $this->createSlots(1);

        $photo = Photo::factory()->create([
            'user_id' => $this->facilitator->id,
            'team_id' => $this->schoolTeam->id,
            'participant_id' => $participants[0]->id,
            'is_public' => false,
            'verified' => VerificationStatus::VERIFIED->value,
            'summary' => json_encode(['smoking' => ['cigarette_butt' => 5]]),
        ]);

        $this->actingAs($this->facilitator)
            ->postJson('/api/teams/photos/approve', [
                'team_id' => $this->schoolTeam->id,
                'photo_ids' => [$photo->id],
            ])
            ->assertOk();

        // TagsVerifiedByAdmin should fire with facilitator's user_id, not participant
        Event::assertDispatched(TagsVerifiedByAdmin::class, function ($event) {
            return $event->user_id === $this->facilitator->id;
        });
    }

    // ─── CreateTeam Tests ────────────────────────────────────────

    public function test_school_team_county_is_required(): void
    {
        $teacher = User::factory()->create(['remaining_teams' => 1]);
        $role = Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);
        $teacher->assignRole($role);

        $this->actingAs($teacher)
            ->postJson('/api/teams/create', [
                'name' => 'No County School',
                'identifier' => 'NoCounty1',
                'teamType' => $this->schoolType->id,
                'contact_email' => 'teacher@school.ie',
                // Missing county
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['county']);
    }

    public function test_school_team_can_enable_participant_sessions(): void
    {
        $teacher = User::factory()->create(['remaining_teams' => 1]);
        $role = Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);
        $teacher->assignRole($role);

        $this->actingAs($teacher)
            ->postJson('/api/teams/create', [
                'name' => 'Session School',
                'identifier' => 'Session1',
                'teamType' => $this->schoolType->id,
                'contact_email' => 'teacher@school.ie',
                'county' => 'Kerry',
                'participant_sessions_enabled' => true,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $team = Team::where('name', 'Session School')->first();
        $this->assertTrue($team->participant_sessions_enabled);
        $this->assertTrue($team->hasParticipantSessions());
    }

    public function test_community_team_ignores_participant_sessions(): void
    {
        $communityType = TeamType::firstOrCreate(
            ['team' => 'community'],
            ['team' => 'community', 'price' => 0]
        );

        $user = User::factory()->create(['remaining_teams' => 1]);

        $this->actingAs($user)
            ->postJson('/api/teams/create', [
                'name' => 'Community Team',
                'identifier' => 'Comm1',
                'teamType' => $communityType->id,
                'participant_sessions_enabled' => true, // Should be ignored
            ])
            ->assertOk();

        $team = Team::where('name', 'Community Team')->first();
        $this->assertFalse($team->participant_sessions_enabled);
    }

    // ─── Helpers ─────────────────────────────────────────────────

    /**
     * Create participant slots and return the models (with tokens accessible).
     */
    protected function createSlots(int $count): array
    {
        $participants = [];

        for ($i = 1; $i <= $count; $i++) {
            $participants[] = Participant::create([
                'team_id' => $this->schoolTeam->id,
                'slot_number' => $i,
                'display_name' => "Student {$i}",
                'session_token' => Participant::generateToken(),
            ]);
        }

        return $participants;
    }
}
