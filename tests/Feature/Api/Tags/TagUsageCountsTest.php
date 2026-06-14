<?php

namespace Tests\Feature\Api\Tags;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class TagUsageCountsTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = sys_get_temp_dir().'/tag_usage_counts_'.uniqid().'.json';
        config(['tags.usage_counts_path' => $this->path]);
    }

    protected function tearDown(): void
    {
        if (File::exists($this->path)) {
            File::delete($this->path);
        }

        parent::tearDown();
    }

    public function test_tags_all_embeds_the_counts_map(): void
    {
        File::put($this->path, json_encode([
            'generated_at' => '2026-06-14',
            'scope' => 'total_recorded_tags',
            'counts' => ['5:16:0' => 37559, '5:2:0' => 19288],
        ]));

        $response = $this->getJson('/api/tags/all');

        $response->assertOk();
        $response->assertJsonPath('tag_usage_counts.5:16:0', 37559);
        $response->assertJsonPath('tag_usage_counts.5:2:0', 19288);
    }

    public function test_missing_file_degrades_to_empty_map(): void
    {
        $this->assertFalse(File::exists($this->path));

        $response = $this->getJson('/api/tags/all');

        $response->assertOk();
        $response->assertJsonPath('tag_usage_counts', []);
    }

    public function test_empty_file_degrades_to_empty_map(): void
    {
        File::put($this->path, '');

        $response = $this->getJson('/api/tags/all');

        $response->assertOk();
        $response->assertJsonPath('tag_usage_counts', []);
    }
}
