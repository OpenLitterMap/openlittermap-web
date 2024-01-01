<?php

namespace Tests\Feature;

use App\Models\Cluster;
use Tests\TestCase;

class GetClustersTest extends TestCase
{

    public function test_it_lists_global_clusters()
    {
        $globalCluster = Cluster::factory()->create(['zoom' => 2]);

        $response = $this->get('/global/clusters?zoom=2');

        $response->assertStatus(200);

        $features = $response->json('features');
        $this->assertCount(1, $features);
        $this->assertEquals($globalCluster->lon, $features[0]['geometry']['coordinates'][0]);
        $this->assertEquals($globalCluster->lat, $features[0]['geometry']['coordinates'][1]);
    }
}
