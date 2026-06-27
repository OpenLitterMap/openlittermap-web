<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\EmailEvent;
use App\Models\EmailSuppression;
use Aws\Sns\Message;
use Aws\Sns\MessageValidator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Receives AWS SES bounce/complaint notifications delivered via SNS and turns
 * them into suppressions. Signature and topic checks run before anything else.
 *
 * The route is CSRF-exempt (external POST, no session) and reads the raw body
 * directly — SNS posts Content-Type: text/plain, so $request->all() is empty.
 * The MessageValidator is resolved from the container so tests can swap it.
 */
class SesSnsWebhookController extends Controller
{
    public function __invoke(Request $request, MessageValidator $validator): Response
    {
        try {
            $message = Message::fromJsonString($request->getContent());
        } catch (Throwable) {
            return response('Bad request', 400);
        }

        if (! $validator->isValid($message)) {
            return response('Invalid signature', 403);
        }

        if (($message['TopicArn'] ?? null) !== config('services.ses.topic_arn')) {
            return response('Unexpected topic', 403);
        }

        if ($message['Type'] === 'SubscriptionConfirmation') {
            if (! empty($message['SubscribeURL'])) {
                // If confirmation fails, return non-2xx so SNS retries delivery
                // — otherwise the subscription is left silently pending.
                if (! Http::get($message['SubscribeURL'])->successful()) {
                    return response('Subscription confirmation failed', 502);
                }
            }

            return response('OK', 200);
        }

        if ($message['Type'] === 'Notification') {
            $this->handleNotification($message);
        }

        // Always 200 for handled/ignored events so SNS stops retrying.
        return response('OK', 200);
    }

    private function handleNotification(Message $message): void
    {
        $ses = json_decode((string) $message['Message'], true);

        if (! is_array($ses)) {
            return;
        }

        $type = $ses['notificationType'] ?? $ses['eventType'] ?? null;
        $snsId = (string) $message['MessageId'];

        if ($type === 'Bounce') {
            $this->handleBounce($snsId, $ses);
        } elseif ($type === 'Complaint') {
            $this->handleComplaint($snsId, $ses);
        }
        // Delivery / other event types are not stored unless explicitly enabled.
    }

    private function handleBounce(string $snsId, array $ses): void
    {
        $bounce = $ses['bounce'] ?? [];
        $subtype = $bounce['bounceType'] ?? null;
        $at = $this->timestamp($bounce['timestamp'] ?? null);

        // Transient bounces are soft — ignore entirely.
        if (strtoupper((string) $subtype) === 'TRANSIENT') {
            return;
        }

        $suppress = strtoupper((string) $subtype) === 'PERMANENT';

        foreach ($bounce['bouncedRecipients'] ?? [] as $recipient) {
            $email = $this->normalize($recipient['emailAddress'] ?? '');

            if ($email === '') {
                continue;
            }

            $this->recordEvent($snsId, $email, 'Bounce', $subtype, $ses);

            // Undetermined is logged but not suppressed; only Permanent suppresses.
            if ($suppress) {
                EmailSuppression::suppress($email, 'bounced', 'ses', $at);
            }
        }
    }

    private function handleComplaint(string $snsId, array $ses): void
    {
        $complaint = $ses['complaint'] ?? [];
        $subtype = $complaint['complaintFeedbackType'] ?? null;
        $at = $this->timestamp($complaint['timestamp'] ?? null);

        foreach ($complaint['complainedRecipients'] ?? [] as $recipient) {
            $email = $this->normalize($recipient['emailAddress'] ?? '');

            if ($email === '') {
                continue;
            }

            $this->recordEvent($snsId, $email, 'Complaint', $subtype, $ses);
            EmailSuppression::suppress($email, 'complained', 'ses', $at);
        }
    }

    /**
     * Idempotent on (sns_id, email) — a duplicate SNS delivery is harmless.
     */
    private function recordEvent(string $snsId, string $email, string $type, ?string $subtype, array $payload): void
    {
        EmailEvent::firstOrCreate(
            ['sns_id' => $snsId, 'email' => $email],
            ['type' => $type, 'subtype' => $subtype, 'payload' => $payload],
        );
    }

    private function timestamp(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function normalize(string $email): string
    {
        return strtolower(trim($email));
    }
}
