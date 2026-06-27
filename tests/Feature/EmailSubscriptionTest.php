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

    public function test_a_visitor_can_subscribe_with_a_valid_email(): void
    {
        $response = $this->postJson('/subscribe', ['email' => 'newsfan@example.com']);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('subscribers', ['email' => 'newsfan@example.com']);
    }

    /**
     * The exact class of address that broke the SES mass-send: a syntactically
     * RFC-valid email whose domain has no dot (e.g. the legacy `Estherbarriga@8`).
     * Laravel's default `email` rule accepts these, so the dotted-domain regex
     * is what rejects them.
     *
     * @dataProvider dotlessDomainEmails
     */
    public function test_subscribe_rejects_an_email_with_a_dotless_domain(string $email): void
    {
        $response = $this->postJson('/subscribe', ['email' => $email]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
        $this->assertDatabaseMissing('subscribers', ['email' => $email]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function dotlessDomainEmails(): array
    {
        return [
            'bare numeric domain' => ['Estherbarriga@8'],
            'single letter domain' => ['6@g'],
            'word domain no tld' => ['test@test'],
            'gmail missing dot' => ['kateliya@gmail'],
            'gmailcom missing dot' => ['gladisperedo7589@gmailcom'],
            'tld only as domain' => ['sandra.benbeniste@com'],
        ];
    }

    public function test_subscribe_accepts_multi_level_and_uppercase_domains(): void
    {
        $this->postJson('/subscribe', ['email' => 'a@sub.domain.co.uk'])->assertOk();
        $this->postJson('/subscribe', ['email' => 'b@Example.COM'])->assertOk();

        $this->assertDatabaseHas('subscribers', ['email' => 'a@sub.domain.co.uk']);
        $this->assertDatabaseHas('subscribers', ['email' => 'b@Example.COM']);
    }

    public function test_subscribe_rejects_a_duplicate_email(): void
    {
        Subscriber::create(['email' => 'already@example.com']);

        $response = $this->postJson('/subscribe', ['email' => 'already@example.com']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
        $this->assertEquals(1, Subscriber::where('email', 'already@example.com')->count());
    }

    public function test_subscribe_requires_an_email(): void
    {
        $response = $this->postJson('/subscribe', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
    }
}
