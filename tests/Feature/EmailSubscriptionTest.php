<?php

namespace Tests\Feature;

use App\Models\Users\User;
use App\Subscriber;
use Tests\TestCase;

class EmailSubscriptionTest extends TestCase
{
    public function test_user_can_unsubscribe_via_token_link(): void
    {
        $user = User::factory()->create(['emailsub' => 1]);

        $response = $this->get('/emails/unsubscribe/' . $user->sub_token);

        $response->assertRedirect('/?unsub=1');
        $this->assertEquals(0, $user->fresh()->emailsub);
    }

    public function test_unsubscribe_with_invalid_token_still_redirects(): void
    {
        $response = $this->get('/emails/unsubscribe/invalid-token-12345');

        $response->assertRedirect('/?unsub=1');
    }

    public function test_user_can_toggle_email_subscription_via_api(): void
    {
        $user = User::factory()->create(['emailsub' => 1]);

        $response = $this->actingAs($user)->postJson('/api/settings/email/toggle');

        $response->assertOk();
        $response->assertJson(['sub' => false]);
        $this->assertEquals(0, $user->fresh()->emailsub);
    }

    public function test_user_can_resubscribe_via_api(): void
    {
        $user = User::factory()->create(['emailsub' => 0]);

        $response = $this->actingAs($user)->postJson('/api/settings/email/toggle');

        $response->assertOk();
        $response->assertJson(['sub' => true]);
        $this->assertEquals(1, $user->fresh()->emailsub);
    }

    public function test_user_can_update_emailsub_via_settings(): void
    {
        $user = User::factory()->create(['emailsub' => 1]);

        $response = $this->actingAs($user)->postJson('/api/settings/update', [
            'key' => 'emailsub',
            'value' => false,
        ]);

        $response->assertOk();
        $this->assertEquals(0, $user->fresh()->emailsub);
    }

    public function test_user_can_resubscribe_via_settings(): void
    {
        $user = User::factory()->create(['emailsub' => 0]);

        $response = $this->actingAs($user)->postJson('/api/settings/update', [
            'key' => 'emailsub',
            'value' => true,
        ]);

        $response->assertOk();
        $this->assertEquals(1, $user->fresh()->emailsub);
    }

    public function test_unauthenticated_user_cannot_toggle_email_subscription(): void
    {
        $response = $this->postJson('/api/settings/email/toggle');

        $response->assertUnauthorized();
    }

    public function test_subscriber_can_unsubscribe_via_token_link(): void
    {
        $subscriber = Subscriber::create(['email' => 'sub@example.com']);

        $response = $this->get('/emails/unsubscribe/' . $subscriber->sub_token);

        $response->assertRedirect('/?unsub=1');
        $this->assertNull(Subscriber::find($subscriber->id));
    }

    public function test_subscriber_token_is_auto_generated(): void
    {
        $subscriber = Subscriber::create(['email' => 'token@example.com']);

        $this->assertNotNull($subscriber->sub_token);
        $this->assertEquals(30, strlen($subscriber->sub_token));
    }
}
