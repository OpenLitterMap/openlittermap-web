<?php

namespace Tests\Feature\Api\Users;

use App\Models\Users\User;
use Tests\TestCase;

class FetchUserTest extends TestCase
{
    public function test_it_contains_all_necessary_fields()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/user/profile/index')
            ->assertOk()
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                    'username',
                    'email',
                    'created_at',
                    'picked_up',
                ],
                'stats' => [
                    'uploads',
                    'litter',
                    'xp',
                ],
                'level',
                'rank' => [
                    'global_position',
                    'global_total',
                ],
            ]);
    }
}
