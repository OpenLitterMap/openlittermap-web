<?php

namespace Tests\Feature\Api\Tags;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MostTaggedTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = sys_get_temp_dir().'/tag_counts_public_'.uniqid().'.json';
        config(['tags.public_counts_path' => $this->path]);
    }

    protected function tearDown(): void
    {
        if (File::exists($this->path)) {
            File::delete($this->path);
        }

        parent::tearDown();
    }

    public function test_returns_ranked_coarse_list_summing_per_type_buckets(): void
    {
        File::put($this->path, json_encode([
            'generated_at' => '2026-06-14',
            'scope' => 'verified_public_on_map',
            'counts' => [
                '5:16:24' => 100,
                '5:16:26' => 50,  // same object+category, different type → coarse sum of 150
                '9:8:0' => 200,   // highest
                '1:2:0' => 30,    // lowest
            ],
        ]));

        $response = $this->getJson('/api/tags/most-tagged');

        $response->assertOk();
        $response->assertJsonPath('scope', 'verified_public_on_map');
        $response->assertJsonPath('generated_at', '2026-06-14');
        $response->assertJsonCount(3, 'most_tagged');
        $response->assertJsonPath('most_tagged.0', ['object_id' => 9, 'category_id' => 8, 'count' => 200]);
        $response->assertJsonPath('most_tagged.1', ['object_id' => 5, 'category_id' => 16, 'count' => 150]);
        $response->assertJsonPath('most_tagged.2', ['object_id' => 1, 'category_id' => 2, 'count' => 30]);
    }

    public function test_zero_count_pairs_are_excluded(): void
    {
        File::put($this->path, json_encode([
            'generated_at' => '2026-06-14',
            'scope' => 'verified_public_on_map',
            'counts' => [
                '9:8:0' => 5,
                '7:2:0' => 0,  // zero → excluded
            ],
        ]));

        $response = $this->getJson('/api/tags/most-tagged');

        $response->assertOk();
        $response->assertJsonCount(1, 'most_tagged');
        $response->assertJsonPath('most_tagged.0.object_id', 9);
    }

    public function test_missing_file_returns_empty_list(): void
    {
        $this->assertFalse(File::exists($this->path));

        $response = $this->getJson('/api/tags/most-tagged');

        $response->assertOk();
        $response->assertJsonPath('most_tagged', []);
        $response->assertJsonPath('scope', null);
        $response->assertJsonPath('generated_at', null);
    }

    public function test_malformed_file_returns_empty_list(): void
    {
        File::put($this->path, 'not valid json{');

        $response = $this->getJson('/api/tags/most-tagged');

        $response->assertOk();
        $response->assertJsonPath('most_tagged', []);
    }
}
