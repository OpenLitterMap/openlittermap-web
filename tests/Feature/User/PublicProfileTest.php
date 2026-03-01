<?php

namespace Tests\Feature\User;

use App\Models\Users\User;
use Tests\TestCase;

class PublicProfileTest extends TestCase
{
    /** @test */
    public function public_profile_returns_user_data(): void
    {
        $user = User::factory()->create([
            'name' => 'Public User',
            'username' => 'publicuser',
            'public_profile' => true,
            'show_name' => true,
            'show_username' => true,
        ]);

        $response = $this->getJson("/api/user/profile/{$user->id}");

        $response->assertOk();
        $response->assertJsonPath('public', true);
        $response->assertJsonPath('user.name', 'Public User');
        $response->assertJsonPath('user.username', 'publicuser');
        $response->assertJsonStructure([
            'user' => ['id', 'name', 'username', 'avatar', 'global_flag', 'member_since'],
            'stats' => ['uploads', 'litter', 'xp'],
            'level' => ['level', 'title'],
            'rank' => ['global_position', 'global_total', 'percentile'],
            'achievements' => ['unlocked', 'total'],
            'locations' => ['countries', 'states', 'cities'],
        ]);
    }

    /** @test */
    public function private_profile_returns_public_false(): void
    {
        $user = User::factory()->create(['public_profile' => false]);

        $response = $this->getJson("/api/user/profile/{$user->id}");

        $response->assertOk();
        $response->assertJsonPath('public', false);
        $response->assertJsonMissing(['stats']);
    }

    /** @test */
    public function public_profile_respects_privacy_settings(): void
    {
        $user = User::factory()->create([
            'name' => 'Hidden Name',
            'username' => 'hiddenuser',
            'public_profile' => true,
            'show_name' => false,
            'show_username' => false,
        ]);

        $response = $this->getJson("/api/user/profile/{$user->id}");

        $response->assertOk();
        $response->assertJsonPath('public', true);
        $response->assertJsonPath('user.name', null);
        $response->assertJsonPath('user.username', null);
    }

    /** @test */
    public function nonexistent_user_returns_404(): void
    {
        $response = $this->getJson('/api/user/profile/999999');

        $response->assertNotFound();
    }
}
