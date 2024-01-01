<?php

namespace Tests\Unit\Commands;

use App\Models\Photo;
use App\Models\TeamCluster;
use App\Models\Teams\Team;
use Tests\TestCase;

class GenerateTeamClustersTest extends TestCase
{
    public function test_it_generates_team_clusters()
    {
        $team = Team::factory()->create(['total_images' => 5]);
        Photo::factory(5)->create([
            'lat' => 0,
            'lon' => 0,
            'team_id' => $team->id
        ]);

        $this->artisan('clusters:generate-team-clusters');

        // Zoom levels from 2-16, 1 cluster per level
        $this->assertSame(15, TeamCluster::count());
        $this->assertEquals($team->id, TeamCluster::first()->team_id);
    }
}
