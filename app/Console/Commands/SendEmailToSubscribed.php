<?php

namespace App\Console\Commands;

use App\Jobs\Emails\DispatchEmail;
use App\Models\EmailSend;
use App\Models\EmailSuppression;
use App\Models\Users\User;
use App\Subscriber;
use App\Support\EmailAddress;
use App\Support\EmailRecipient;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Sends a campaign email to subscribed users (emailsub=1) and standalone
 * subscribers, recording every recipient in the `email_sends` ledger.
 *
 * The ledger makes a send runnable, interruptible, resumable, and auditable:
 * the (campaign, email) unique key is the atomic claim lock, re-runs only
 * dispatch addresses with no accepted/queued row, and previously-skipped rows
 * are re-evaluated each run (an address can become sendable later).
 */
class SendEmailToSubscribed extends Command
{
    protected $signature = 'olm:send-email-to-subscribed
        {--campaign= : Campaign key (required for a bulk send), e.g. update28}
        {--dry-run : Report only — claim and dispatch nothing}
        {--chunk=100 : Rows to load per batch}
        {--limit= : Cap the number of dispatches (use for the first real run)}
        {--only-email= : Send a single untracked preview to this address}
        {--retry-failed : Re-claim rows in status=failed}
        {--retry-stale-queued= : Re-claim queued rows older than N seconds (crash recovery)}';

    protected $description = 'Send a campaign email to subscribed users and subscribers (ledger-tracked, resumable).';

    private const CONFIRM_PROMPT = 'Queue these emails now?';

    /** Floor for --retry-stale-queued: a queued row must be older than this to reclaim. */
    private const MIN_STALE_SECONDS = 60;

    /** @var array<string, true> normalized suppressed emails */
    private array $suppressed = [];

    public function handle(): int
    {
        // ─── Single untracked preview (no campaign, no ledger) ───────────
        if ($only = $this->option('only-email')) {
            $email = $this->normalize($only);
            DispatchEmail::dispatch(null, new EmailRecipient('user', 0, $email, 'preview-token'));
            $this->info("Preview email dispatched to {$email}.");

            return self::SUCCESS;
        }

        $campaign = $this->option('campaign');

        if (! $campaign) {
            $this->error('The --campaign option is required (e.g. --campaign=update28).');

            return self::FAILURE;
        }

        $chunk = max(1, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $retryFailed = (bool) $this->option('retry-failed');

        $retryStale = $this->option('retry-stale-queued');

        if ($retryStale !== null && (! ctype_digit((string) $retryStale) || (int) $retryStale < self::MIN_STALE_SECONDS)) {
            $this->error('--retry-stale-queued must be a positive integer of at least ' . self::MIN_STALE_SECONDS
                . ' (seconds). A small value would reclaim genuinely in-flight rows and double-send.');

            return self::FAILURE;
        }

        $staleThreshold = $retryStale !== null ? Carbon::now()->subSeconds((int) $retryStale) : null;

        $this->suppressed = EmailSuppression::query()->pluck('email')
            ->mapWithKeys(fn ($e) => [$this->normalize($e) => true])
            ->all();

        // ─── Tally (read-only) + report ──────────────────────────────────
        $verdicts = ['accepted' => 0, 'queued' => 0, 'failed' => 0, 'invalid' => 0, 'suppressed' => 0, 'dispatch' => 0];

        $this->eachCandidate($chunk, function (string $email) use ($campaign, $staleThreshold, $retryFailed, &$verdicts) {
            $verdicts[$this->decide($campaign, $email, $staleThreshold, $retryFailed)]++;

            return true;
        });

        $userEmails = User::withoutGlobalScopes()->select('email');
        $eligibleUsers = $this->userQuery()->where('emailsub', 1)->count();
        $eligibleSubscribers = Subscriber::whereNotIn('email', $userEmails)->count();
        $duplicate = Subscriber::count() - $eligibleSubscribers;
        $willDispatch = $limit !== null ? min($verdicts['dispatch'], $limit) : $verdicts['dispatch'];

        $this->info("Campaign: {$campaign}");
        $this->line($this->reportLine('Eligible users:', $eligibleUsers));
        $this->line($this->reportLine('Eligible subscribers:', $eligibleSubscribers));
        $this->line($this->reportLine('Deduped total:', $eligibleUsers + $eligibleSubscribers));
        $this->line($this->reportLine('Invalid skipped:', $verdicts['invalid']));
        $this->line($this->reportLine('Suppressed skipped:', $verdicts['suppressed']));
        $this->line($this->reportLine('Duplicate skipped:', $duplicate));
        $this->line($this->reportLine('Already accepted:', $verdicts['accepted']));
        $this->line($this->reportLine('Will dispatch now:', $willDispatch));

        if ($dryRun) {
            $this->info('Dry run — nothing claimed or dispatched.');

            return self::SUCCESS;
        }

        if (! $this->confirm(self::CONFIRM_PROMPT)) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        // ─── Execute: claim + dispatch ───────────────────────────────────
        $dispatched = 0;

        $this->eachCandidate($chunk, function (string $email, string $type, int $id, ?string $token)
            use ($campaign, $staleThreshold, $retryFailed, $limit, &$dispatched) {
            if ($limit !== null && $dispatched >= $limit) {
                return false;
            }

            $verdict = $this->decide($campaign, $email, $staleThreshold, $retryFailed);

            if ($verdict === 'invalid') {
                $this->logSkip($campaign, $email, $type, $id, 'skipped_invalid');

                return true;
            }

            if ($verdict === 'suppressed') {
                $this->logSkip($campaign, $email, $type, $id, 'skipped_suppressed');

                return true;
            }

            if ($verdict !== 'dispatch') {
                return true; // accepted / queued (in-flight) / failed (terminal) → leave as-is
            }

            $rowId = $this->claim($campaign, $email, $type, $id, $retryFailed, $staleThreshold);

            if ($rowId === null) {
                return true; // another worker won the claim
            }

            DispatchEmail::dispatch($rowId, new EmailRecipient($type, $id, $email, (string) $token));
            $dispatched++;

            return true;
        });

        $this->info("Done. {$dispatched} emails dispatched to queue.");

        return self::SUCCESS;
    }

    /**
     * Iterate users (emailsub=1) then subscribers deduped against ALL user
     * emails. The callback receives (email, type, id, token); returning false
     * stops iteration. Subscribers whose email belongs to any user are skipped
     * here (the user pass governs them) and counted as duplicates in the report.
     */
    private function eachCandidate(int $chunk, callable $cb): void
    {
        $userEmails = User::withoutGlobalScopes()->select('email');
        $stopped = false;

        $this->userQuery()->where('emailsub', 1)->orderBy('id')->select('id', 'email', 'sub_token')
            ->chunk($chunk, function ($users) use ($cb, &$stopped) {
                foreach ($users as $u) {
                    if ($cb($this->normalize($u->email), 'user', $u->id, $u->sub_token) === false) {
                        $stopped = true;

                        return false;
                    }
                }

                return true;
            });

        if ($stopped) {
            return;
        }

        Subscriber::whereNotIn('email', $userEmails)->orderBy('id')->select('id', 'email', 'sub_token')
            ->chunk($chunk, function ($subs) use ($cb) {
                foreach ($subs as $s) {
                    if ($cb($this->normalize($s->email), 'subscriber', $s->id, $s->sub_token) === false) {
                        return false;
                    }
                }

                return true;
            });
    }

    /**
     * Lean user query for the bulk send: drops the photosCount global scope and
     * the model's default eager loads ($with), which are pure overhead here.
     */
    private function userQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return User::query()->withoutGlobalScope('photosCount')->setEagerLoads([]);
    }

    /**
     * Classify a candidate without writing anything.
     *
     * @return string accepted|queued|failed|invalid|suppressed|dispatch
     */
    private function decide(string $campaign, string $email, ?Carbon $staleThreshold, bool $retryFailed): string
    {
        $row = EmailSend::query()
            ->where('campaign', $campaign)->where('email', $email)
            ->first(['status', 'updated_at']);

        if ($row) {
            if ($row->status === 'accepted') {
                return 'accepted';
            }

            if ($row->status === 'queued') {
                $stale = $staleThreshold && $row->updated_at && $row->updated_at->lt($staleThreshold);

                if (! $stale) {
                    return 'queued';
                }
            } elseif ($row->status === 'failed' && ! $retryFailed) {
                return 'failed';
            }
            // skipped_* / failed(+retry) / stale-queued → re-evaluate below
        }

        if (! EmailAddress::isSendable($email)) {
            return 'invalid';
        }

        if (isset($this->suppressed[$email])) {
            return 'suppressed';
        }

        return 'dispatch';
    }

    /**
     * Atomically claim a row for dispatch: re-claim a reclaimable existing row,
     * else insert a fresh one. The unique key blocks concurrent double-claims.
     * Returns the ledger row id, or null if another worker already owns it.
     */
    private function claim(string $campaign, string $email, string $type, int $id, bool $retryFailed, ?Carbon $staleThreshold): ?int
    {
        $now = Carbon::now();
        $reclaimable = ['skipped_invalid', 'skipped_suppressed'];

        if ($retryFailed) {
            $reclaimable[] = 'failed';
        }

        $updated = EmailSend::query()
            ->where('campaign', $campaign)->where('email', $email)
            ->where(function ($q) use ($reclaimable, $staleThreshold) {
                $q->whereIn('status', $reclaimable);

                if ($staleThreshold) {
                    $q->orWhere(fn ($q2) => $q2->where('status', 'queued')->where('updated_at', '<', $staleThreshold));
                }
            })
            ->update([
                'status' => 'queued',
                'recipient_type' => $type,
                'recipient_id' => $id,
                'updated_at' => $now,
            ]);

        if ($updated === 0) {
            $inserted = EmailSend::insertOrIgnore([
                'campaign' => $campaign,
                'email' => $email,
                'recipient_type' => $type,
                'recipient_id' => $id,
                'status' => 'queued',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if (! $inserted) {
                return null; // already queued/accepted, or a concurrent worker won
            }
        }

        return EmailSend::query()->where('campaign', $campaign)->where('email', $email)->value('id');
    }

    private function logSkip(string $campaign, string $email, string $type, int $id, string $status): void
    {
        EmailSend::query()->updateOrInsert(
            ['campaign' => $campaign, 'email' => $email],
            ['recipient_type' => $type, 'recipient_id' => $id, 'status' => $status, 'updated_at' => Carbon::now()],
        );
    }

    private function normalize(string $email): string
    {
        return strtolower(trim($email));
    }

    private function reportLine(string $label, int $value): string
    {
        return sprintf('%-23s %s', $label, $value);
    }
}
