<?php

namespace Api;

use App\Models\User\User;
use Tests\TestCase;

class FetchUserTest extends TestCase
{
    public function test_it_contains_all_necessary_fields()
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'api')
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonStructure([
                "name",
                "username",
                "email",
                "verified",
                "images_remaining",
                "token",
                "sub_token",
                "updated_at",
                "created_at",
                "id",
                "total_categories",
                "total_tags",
                "total_brands_redis",
                "position",
            ]);
    }

}
