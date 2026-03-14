<?php

namespace Tests\Feature\Map\Clusters;

use App\Models\Teams\Team;
use App\Models\Teams\TeamType;
use App\Models\Users\User;
use App\Services\Clustering\ClusteringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Helpers\CreateTestClusterPhotosTrait;
use Tests\TestCase;

class TeamClusteringTest extends TestCase
{
    use RefreshDatabase, CreateTestClusterPhotosTrait;

    private ClusteringService $service;
    private Team $team;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCreateTestClusterPhotos();
        $this->service = app(ClusteringService::class);

        $this->user = User::factory()->create();
        $type = TeamType::first() ?? TeamType::factory()->create();
        $this->team = Team::factory()->create([
            'type_id' => $type->id,
            'created_by' => $this->user->id,
        ]);
    }

    protected function tearDown(): void
    {
        $this->cleanupTestPhotos();
        parent::tearDown();
    }

    /** @test */
    public function cluster_team_generates_clusters_for_team_photos(): void
    {
        $this->createPhotosAt(51.5, -0.1, 10, [
            'team_id' => $this->team->id,
            'user_id' => $this->user->id,
            'verified' => 2,
        ]);

        $this->service->backfillPhotoTileKeys();
        $count = $this->service->clusterTeam($this->team->id);

        $this->assertGreaterThan(0, $count);

        // Verify clusters belong to this team
        $clusters = DB::table('clusters')
            ->where('team_id', $this->team->id)
            ->get();

        $this->assertGreaterThan(0, $clusters->count());
        $this->assertTrue($clusters->every(fn($c) => $c->team_id == $this->team->id));
    }

    /** @test */
    public function team_clusters_are_scoped_to_their_team(): void
    {
        $type = TeamType::first();
        $teamB = Team::factory()->create([
            'type_id' => $type->id,
            'created_by' => $this->user->id,
        ]);

        // Create photos for team A
        $this->createPhotosAt(51.5, -0.1, 5, [
            'team_id' => $this->team->id,
            'user_id' => $this->user->id,
            'verified' => 2,
        ]);

        // Create photos for team B at a different location
        $this->createPhotosAt(48.8, 2.3, 5, [
            'team_id' => $teamB->id,
            'user_id' => $this->user->id,
            'verified' => 2,
        ]);

        $this->service->backfillPhotoTileKeys();
        $this->service->clusterTeam($this->team->id);
        $this->service->clusterTeam($teamB->id);

        // Team A clusters should not include team B's photos
        $teamAClusters = DB::table('clusters')->where('team_id', $this->team->id)->get();
        $teamBClusters = DB::table('clusters')->where('team_id', $teamB->id)->get();

        $this->assertGreaterThan(0, $teamAClusters->count());
        $this->assertGreaterThan(0, $teamBClusters->count());

        // Check that point counts are based on each team's own photos (5 each)
        $teamAPoints = $teamAClusters->where('zoom', 16)->sum('point_count');
        $teamBPoints = $teamBClusters->where('zoom', 16)->sum('point_count');

        $this->assertEquals(5, $teamAPoints);
        $this->assertEquals(5, $teamBPoints);
    }

    /** @test */
    public function team_clusters_do_not_interfere_with_global_clusters(): void
    {
        // Create photos: some with team, some without
        $this->createPhotosAt(51.5, -0.1, 10, [
            'team_id' => $this->team->id,
            'user_id' => $this->user->id,
            'verified' => 2,
        ]);
        $this->createPhotosAt(48.8, 2.3, 10, [
            'user_id' => $this->user->id,
            'verified' => 2,
        ]);

        $this->service->backfillPhotoTileKeys();

        // Run global clustering
        $globalCount = $this->service->clusterGlobal(0);

        // Run team clustering
        $teamCount = $this->service->clusterTeam($this->team->id);

        // Global clusters should have team_id=0
        $globalClusters = DB::table('clusters')->where('team_id', 0)->where('zoom', 0)->count();
        $this->assertEquals($globalCount, $globalClusters);

        // Team clusters should have the team's ID
        $teamClusters = DB::table('clusters')->where('team_id', $this->team->id)->count();
        $this->assertGreaterThan(0, $teamClusters);

        // Re-running global should NOT delete team clusters
        $this->service->clusterGlobal(0);
        $teamClustersAfter = DB::table('clusters')->where('team_id', $this->team->id)->count();
        $this->assertEquals($teamClusters, $teamClustersAfter);
    }

    /** @test */
    public function empty_team_returns_zero_clusters(): void
    {
        $count = $this->service->clusterTeam($this->team->id);
        $this->assertEquals(0, $count);
    }

    /** @test */
    public function nonexistent_team_returns_zero_clusters(): void
    {
        $count = $this->service->clusterTeam(999999);
        $this->assertEquals(0, $count);
    }

    /** @test */
    public function team_cluster_api_returns_geojson(): void
    {
        $this->createPhotosAt(51.5, -0.1, 5, [
            'team_id' => $this->team->id,
            'user_id' => $this->user->id,
            'verified' => 2,
        ]);

        $this->service->backfillPhotoTileKeys();
        $this->service->clusterTeam($this->team->id);

        $response = $this->actingAs($this->user)
            ->getJson("/api/teams/clusters/{$this->team->id}?zoom=0");

        $response->assertOk();
        $data = $response->json();

        $this->assertEquals('FeatureCollection', $data['type']);
        $this->assertNotEmpty($data['features']);

        // Verify GeoJSON structure
        $feature = $data['features'][0];
        $this->assertEquals('Feature', $feature['type']);
        $this->assertEquals('Point', $feature['geometry']['type']);
        $this->assertArrayHasKey('point_count', $feature['properties']);
        $this->assertTrue($feature['properties']['cluster']);
    }

    /** @test */
    public function team_cluster_api_respects_bbox_filtering(): void
    {
        // London photos
        $this->createPhotosAt(51.5, -0.1, 5, [
            'team_id' => $this->team->id,
            'user_id' => $this->user->id,
            'verified' => 2,
        ]);

        // Sydney photos (far away)
        $this->createPhotosAt(-33.8, 151.2, 5, [
            'team_id' => $this->team->id,
            'user_id' => $this->user->id,
            'verified' => 2,
        ]);

        $this->service->backfillPhotoTileKeys();
        $this->service->clusterTeam($this->team->id);

        // Bbox covering only London area
        $response = $this->actingAs($this->user)
            ->getJson("/api/teams/clusters/{$this->team->id}?" . http_build_query([
                'zoom' => 6,
                'bbox' => ['left' => -10, 'bottom' => 40, 'right' => 10, 'top' => 60],
            ]));

        $response->assertOk();
        $features = $response->json('features');

        // Should only include London clusters, not Sydney
        foreach ($features as $feature) {
            $lat = $feature['geometry']['coordinates'][1];
            $this->assertGreaterThan(40, $lat);
            $this->assertLessThan(60, $lat);
        }
    }

    /** @test */
    public function team_cluster_api_returns_empty_for_no_clusters(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/teams/clusters/{$this->team->id}?zoom=0");

        $response->assertOk();
        $data = $response->json();

        $this->assertEquals('FeatureCollection', $data['type']);
        $this->assertEmpty($data['features']);
    }

    /** @test */
    public function cluster_team_includes_tagged_but_unapproved_photos(): void
    {
        // verified=1 means tagged but not yet admin-approved
        $this->createPhotosAt(51.5, -0.1, 5, [
            'team_id' => $this->team->id,
            'user_id' => $this->user->id,
            'verified' => 1,
        ]);

        $this->service->backfillPhotoTileKeys();
        $count = $this->service->clusterTeam($this->team->id);

        $this->assertGreaterThan(0, $count);

        $points = DB::table('clusters')
            ->where('team_id', $this->team->id)
            ->where('zoom', 16)
            ->sum('point_count');

        $this->assertEquals(5, $points);
    }

    /** @test */
    public function cluster_team_excludes_untagged_photos(): void
    {
        // verified=0 means uploaded but not tagged
        $this->createPhotosAt(51.5, -0.1, 5, [
            'team_id' => $this->team->id,
            'user_id' => $this->user->id,
            'verified' => 0,
        ]);

        $this->service->backfillPhotoTileKeys();
        $count = $this->service->clusterTeam($this->team->id);

        $this->assertEquals(0, $count);
    }

    /** @test */
    public function clustering_update_command_supports_team_option(): void
    {
        $this->createPhotosAt(51.5, -0.1, 5, [
            'team_id' => $this->team->id,
            'user_id' => $this->user->id,
            'verified' => 2,
        ]);

        $this->service->backfillPhotoTileKeys();

        $this->artisan('clustering:update', ['--team' => $this->team->id])
            ->assertExitCode(0);

        $clusters = DB::table('clusters')
            ->where('team_id', $this->team->id)
            ->count();

        $this->assertGreaterThan(0, $clusters);
    }

}
