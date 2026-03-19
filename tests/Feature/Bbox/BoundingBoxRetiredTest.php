<?php

namespace Tests\Feature\Bbox;

use App\Models\Users\User;
use Tests\TestCase;

class BoundingBoxRetiredTest extends TestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['can_bbox' => true]);
    }

    public function test_index_returns_410(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/bbox/index');

        $response->assertStatus(410);
        $response->assertJson(['message' => 'Bounding box endpoints retired in v5. Use the standard tagging flow.']);
    }

    public function test_create_returns_410(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/bbox/create');

        $response->assertStatus(410);
    }

    public function test_skip_returns_410(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/bbox/skip');

        $response->assertStatus(410);
    }

    public function test_update_tags_returns_410(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/bbox/tags/update');

        $response->assertStatus(410);
    }

    public function test_wrong_tags_returns_410(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/bbox/tags/wrong');

        $response->assertStatus(410);
    }
}
