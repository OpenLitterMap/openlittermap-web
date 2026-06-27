<?php

namespace Tests\Feature\Email;

use Aws\Sns\Message;
use Aws\Sns\MessageValidator;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EmailWebhookTest extends TestCase
{
    private const TOPIC = 'arn:aws:sns:eu-west-1:123456789012:ses-events';
    private const URL = '/webhooks/aws/ses/sns';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.ses.topic_arn' => self::TOPIC]);
        $this->fakeValidator(true);
    }

    private function fakeValidator(bool $valid): void
    {
        $this->app->instance(MessageValidator::class, new class($valid) extends MessageValidator {
            public function __construct(private bool $ok)
            {
            }

            public function isValid(Message $message)
            {
                return $this->ok;
            }
        });
    }

    private function notification(array $sesMessage, array $overrides = []): array
    {
        return array_merge([
            'Type' => 'Notification',
            'MessageId' => 'sns-' . uniqid(),
            'TopicArn' => self::TOPIC,
            'Message' => json_encode($sesMessage),
            'Timestamp' => '2026-06-27T10:00:00.000Z',
            'SignatureVersion' => '1',
            'Signature' => 'sig',
            'SigningCertURL' => 'https://sns.eu-west-1.amazonaws.com/cert.pem',
        ], $overrides);
    }

    private function bounce(string $type, array $emails): array
    {
        return [
            'notificationType' => 'Bounce',
            'bounce' => [
                'bounceType' => $type,
                'timestamp' => '2026-06-27T09:59:00.000Z',
                'bouncedRecipients' => array_map(fn ($e) => ['emailAddress' => $e], $emails),
            ],
        ];
    }

    private function complaint(array $emails): array
    {
        return [
            'notificationType' => 'Complaint',
            'complaint' => [
                'timestamp' => '2026-06-27T09:59:00.000Z',
                'complainedRecipients' => array_map(fn ($e) => ['emailAddress' => $e], $emails),
            ],
        ];
    }

    public function test_permanent_bounce_suppresses_recipient(): void
    {
        $this->postJson(self::URL, $this->notification($this->bounce('Permanent', ['bad@example.com'])))
            ->assertOk();

        $this->assertDatabaseHas('email_suppressions', [
            'email' => 'bad@example.com', 'reason' => 'bounced', 'source' => 'ses',
        ]);
        $this->assertDatabaseHas('email_events', [
            'email' => 'bad@example.com', 'type' => 'Bounce', 'subtype' => 'Permanent',
        ]);
    }

    public function test_transient_bounce_does_not_suppress(): void
    {
        $this->postJson(self::URL, $this->notification($this->bounce('Transient', ['soft@example.com'])))
            ->assertOk();

        $this->assertDatabaseMissing('email_suppressions', ['email' => 'soft@example.com']);
    }

    public function test_undetermined_bounce_logged_not_suppressed(): void
    {
        $this->postJson(self::URL, $this->notification($this->bounce('Undetermined', ['maybe@example.com'])))
            ->assertOk();

        $this->assertDatabaseHas('email_events', ['email' => 'maybe@example.com', 'type' => 'Bounce']);
        $this->assertDatabaseMissing('email_suppressions', ['email' => 'maybe@example.com']);
    }

    public function test_complaint_suppresses_recipient(): void
    {
        $this->postJson(self::URL, $this->notification($this->complaint(['angry@example.com'])))
            ->assertOk();

        $this->assertDatabaseHas('email_suppressions', [
            'email' => 'angry@example.com', 'reason' => 'complained', 'source' => 'ses',
        ]);
    }

    public function test_duplicate_sns_notification_is_idempotent(): void
    {
        $payload = $this->notification($this->bounce('Permanent', ['dup@example.com']), ['MessageId' => 'fixed-id-1']);

        $this->postJson(self::URL, $payload)->assertOk();
        $this->postJson(self::URL, $payload)->assertOk();

        $this->assertDatabaseCount('email_events', 1);
        $this->assertDatabaseCount('email_suppressions', 1);
    }

    public function test_invalid_signature_returns_403(): void
    {
        $this->fakeValidator(false);

        $this->postJson(self::URL, $this->notification($this->bounce('Permanent', ['x@example.com'])))
            ->assertStatus(403);

        $this->assertDatabaseCount('email_suppressions', 0);
    }

    public function test_wrong_topic_arn_returns_403(): void
    {
        $this->postJson(self::URL, $this->notification(
            $this->bounce('Permanent', ['x@example.com']),
            ['TopicArn' => 'arn:aws:sns:eu-west-1:999:not-our-topic']
        ))->assertStatus(403);

        $this->assertDatabaseCount('email_suppressions', 0);
    }

    public function test_subscription_confirmation_is_confirmed_not_stored(): void
    {
        Http::fake();

        $this->postJson(self::URL, [
            'Type' => 'SubscriptionConfirmation',
            'MessageId' => 'sub-1',
            'Token' => 'tok-123',
            'TopicArn' => self::TOPIC,
            'Message' => 'You have chosen to subscribe',
            'SubscribeURL' => 'https://sns.eu-west-1.amazonaws.com/confirm?Token=tok-123',
            'Timestamp' => '2026-06-27T10:00:00.000Z',
            'SignatureVersion' => '1',
            'Signature' => 'sig',
            'SigningCertURL' => 'https://sns.eu-west-1.amazonaws.com/cert.pem',
        ])->assertOk();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'confirm?Token=tok-123'));
        $this->assertDatabaseCount('email_events', 0);
        $this->assertDatabaseCount('email_suppressions', 0);
    }

    public function test_subscription_confirmation_failure_returns_non_2xx_so_sns_retries(): void
    {
        Http::fake(['*' => Http::response('', 500)]);

        $this->postJson(self::URL, [
            'Type' => 'SubscriptionConfirmation',
            'MessageId' => 'sub-2',
            'Token' => 'tok-456',
            'TopicArn' => self::TOPIC,
            'Message' => 'You have chosen to subscribe',
            'SubscribeURL' => 'https://sns.eu-west-1.amazonaws.com/confirm?Token=tok-456',
            'Timestamp' => '2026-06-27T10:00:00.000Z',
            'SignatureVersion' => '1',
            'Signature' => 'sig',
            'SigningCertURL' => 'https://sns.eu-west-1.amazonaws.com/cert.pem',
        ])->assertStatus(502);
    }
}
