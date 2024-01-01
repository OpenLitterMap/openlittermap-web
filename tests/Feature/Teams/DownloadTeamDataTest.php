<?php

namespace Tests\Feature\Teams;

use Iterator;
use App\Mail\ExportWithLink;
use App\Models\Teams\Team;
use App\Models\User\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DownloadTeamDataTest extends TestCase
{
    public function routeDataProvider(): Iterator
    {
        yield ['guard' => 'web', 'route' => 'teams/download'];
        yield ['guard' => 'api', 'route' => 'api/teams/download'];
    }

    /**
     * @dataProvider routeDataProvider
     */
    public function test_a_member_can_download_a_teams_data($guard, $route)
    {
        Mail::fake();
        Storage::fake('s3');
        Carbon::setTestNow(now());
        /** @var User $member */
        $member = User::factory()->create();
        /** @var Team $team */
        $team = Team::factory()->create();
        $member->teams()->attach($team);

        $response = $this->actingAs($member, $guard)->postJson($route . "?team_id=$team->id");

        $response->assertOk();
        $response->assertJson(['success' => true]);
        Mail::assertSent(function (ExportWithLink $mail) use ($member) {
            $expectedPath = now()->year . "/" . now()->format('m') . "/" . now()->format('d') . "/" . now()->getTimestamp() . "/_Team_OpenLitterMap.csv";
            $this->assertTrue($mail->hasTo($member->email));
            $this->assertSame($expectedPath, $mail->path);
            return true;
        });
    }

    /**
     * @dataProvider routeDataProvider
     */
    public function test_only_a_member_can_download_a_teams_data($guard, $route)
    {
        Mail::fake();
        Storage::fake('s3');
        /** @var User $nonMember */
        $nonMember = User::factory()->create();
        /** @var Team $team */
        $team = Team::factory()->create();

        $response = $this->actingAs($nonMember, $guard)->postJson($route . "?team_id=$team->id");

        $response->assertOk();
        $response->assertJsonFragment(['success' => false, 'message' => 'not-a-member']);
        Mail::assertNothingSent();
    }

}
