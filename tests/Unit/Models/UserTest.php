<?php

namespace Tests\Unit\Models;

use App\Models\Teams\Team;
use App\Models\User\User;
use Tests\TestCase;

class UserTest extends TestCase
{
    public function test_a_user_has_an_is_trusted_attribute()
    {
        $team = Team::factory()->create(['is_trusted' => true]);
        $user = User::factory()->create(['verification_required' => true]);

        $this->assertFalse($user->is_trusted);

        $user->teams()->attach($team);
        $user->active_team = $team->id;
        $user->save();

        $this->assertTrue($user->fresh()->is_trusted);
    }

    public function test_a_user_has_a_picked_up_attribute()
    {
        $user = User::factory()->create(['items_remaining' => true]);

        $this->assertFalse($user->picked_up);

        $user = User::factory()->create(['items_remaining' => false]);

        $this->assertTrue($user->picked_up);
    }

    public function test_a_user_can_get_a_setting()
    {
        $settings = ["settings" => ["foo" => "bar"]];
        /** @var User $user */
        $user = User::factory()->create($settings);

        $this->assertSame("bar", $user->setting("foo"));
        $this->assertNull($user->setting("baz"));
        $this->assertSame(5, $user->setting("baz", 5));
    }

    public function test_a_user_can_change_settings()
    {
        $settings = ["settings" => ["foo" => "bar"]];
        /** @var User $user */
        $user = User::factory()->create($settings);

        $this->assertSame("world", $user->settings(["foo" => "world"])->setting("foo"));
        $this->assertSame("hello", $user->settings(["baz" => "hello"])->setting("baz"));
        $this->assertSame(["foo" => "world", "baz" => 'hello'], $user->fresh()->settings);
    }
}
