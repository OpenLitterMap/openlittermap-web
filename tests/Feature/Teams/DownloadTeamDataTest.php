<?php

namespace Tests\Feature\Teams;

use App\Mail\ExportWithLink;
use App\Models\Teams\Team;
use App\Models\Users\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DownloadTeamDataTest extends TestCase
{
    public function test_a_leader_can_download_a_teams_data()
    {
        Mail::fake();
        Storage::fake('s3');
        Carbon::setTestNow(now());
        /** @var User $leader */
        $leader = User::factory()->create();
        /** @var Team $team */
        $team = Team::factory()->create(['leader' => $leader->id]);
        $leader->teams()->attach($team);

        $response = $this->actingAs($leader)->postJson("api/teams/download?team_id=$team->id");

        $response->assertOk();
        $response->assertJson(['success' => true]);
        Mail::assertSent(function (ExportWithLink $mail) use ($leader) {
            $expectedPath = now()->year . "/" . now()->format('m') . "/" . now()->format('d') . "/" . now()->getTimestamp() . "/_Team_OpenLitterMap.csv";
            $this->assertTrue($mail->hasTo($leader->email));
            $this->assertEquals($expectedPath, $mail->path);
            return true;
        });
    }

    public function test_only_a_member_can_download_a_teams_data()
    {
        Mail::fake();
        Storage::fake('s3');
        /** @var User $nonMember */
        $nonMember = User::factory()->create();
        /** @var Team $team */
        $team = Team::factory()->create();

        $response = $this->actingAs($nonMember)->postJson("api/teams/download?team_id=$team->id");

        $response->assertOk();
        $response->assertJsonFragment(['success' => false, 'message' => 'not-a-member']);
        Mail::assertNothingSent();
    }

    public function test_a_regular_member_cannot_download_a_teams_data()
    {
        Mail::fake();
        Storage::fake('s3');
        /** @var User $member */
        $member = User::factory()->create();
        /** @var User $leader */
        $leader = User::factory()->create();
        /** @var Team $team */
        $team = Team::factory()->create(['leader' => $leader->id]);
        $member->teams()->attach($team);

        $response = $this->actingAs($member)->postJson("api/teams/download?team_id=$team->id");

        $response->assertOk();
        $response->assertJsonFragment(['success' => false, 'message' => 'not-authorized']);
        Mail::assertNothingSent();
    }

    public function test_a_leader_can_download_with_date_filter()
    {
        Mail::fake();
        Storage::fake('s3');
        Carbon::setTestNow(now());
        /** @var User $leader */
        $leader = User::factory()->create();
        /** @var Team $team */
        $team = Team::factory()->create(['leader' => $leader->id]);
        $leader->teams()->attach($team);

        $response = $this->actingAs($leader)->postJson("api/teams/download?team_id=$team->id", [
            'dateField' => 'datetime',
            'fromDate' => '2025-01-01',
            'toDate' => '2025-12-31',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        Mail::assertSent(function (ExportWithLink $mail) use ($leader) {
            $expectedPath = now()->year . "/" . now()->format('m') . "/" . now()->format('d') . "/" . now()->getTimestamp() . "_from_2025-01-01_to_2025-12-31/_Team_OpenLitterMap.csv";
            $this->assertTrue($mail->hasTo($leader->email));
            $this->assertEquals($expectedPath, $mail->path);
            return true;
        });
    }
}
