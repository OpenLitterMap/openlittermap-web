<?php

namespace App\Console\Commands;

use App\Jobs\Emails\DispatchEmail;
use App\Models\EmailSend;
use App\Models\EmailSuppression;
use App\Models\Users\User;
use App\Subscriber;
use App\Support\EmailAddress;
use App\Support\EmailRecipient;
use Generator;
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
 *
 * The flow is a single linear pass: stream each recipient, prepare its ledger
 * row, dispatch or skip. `recipients()` hides the chunked, batch-loaded reads;
 * `prepare()` is the one place ledger state is decided and written.
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
            $email = EmailAddress::normalize($only);
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

        $staleThreshold = $this->staleThreshold();

        if ($staleThreshold === false) {
            return self::FAILURE;
        }

        $this->suppressed = EmailSuppression::query()->pluck('email')
            ->mapWithKeys(fn ($e) => [EmailAddress::normalize($e) => true])
            ->all();

        $this->printPreflight($campaign);

        if (! $dryRun && ! $this->confirm(self::CONFIRM_PROMPT)) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        // ─── The send: stream recipient → prepare ledger → dispatch ──────
        $summary = ['dispatch' => 0, 'accepted' => 0, 'queued' => 0, 'failed' => 0, 'invalid' => 0, 'suppressed' => 0];
        $dispatched = 0;

        foreach ($this->recipients($campaign, $chunk) as [$recipient, $row]) {
            if ($limit !== null && $dispatched >= $limit) {
                break;
            }

            $sendId = $this->prepare($campaign, $recipient, $row, $dryRun, $retryFailed, $staleThreshold, $summary);

            if ($sendId !== null) {
                DispatchEmail::dispatch($sendId, $recipient);
                $dispatched++;
            }
        }

        $this->line($this->reportLine('Will dispatch now:', $summary['dispatch']));
        $this->line($this->reportLine('Invalid skipped:', $summary['invalid']));
        $this->line($this->reportLine('Suppressed skipped:', $summary['suppressed']));

        $this->info($dryRun ? 'Dry run — nothing claimed or dispatched.' : "Done. {$dispatched} emails dispatched to queue.");

        return self::SUCCESS;
    }

    /**
     * Stream candidates — users (emailsub=1) then subscribers deduped against
     * ALL user emails — yielding [EmailRecipient, ?EmailSend ledger row]. Reads
     * keyset-paged (lazyById) and batch-loads each page's ledger rows in one
     * query, so the caller sees a simple linear stream with no N+1.
     *
     * @return Generator<int, array{0: EmailRecipient, 1: ?EmailSend}>
     */
    private function recipients(string $campaign, int $chunk): Generator
    {
        $userEmails = User::withoutGlobalScopes()->select('email');

        yield from $this->streamPass(
            $campaign, $chunk, 'user',
            $this->userQuery()->where('emailsub', 1)->select('id', 'email', 'sub_token')
        );

        yield from $this->streamPass(
            $campaign, $chunk, 'subscriber',
            Subscriber::whereNotIn('email', $userEmails)->select('id', 'email', 'sub_token')
        );
    }

    /**
     * @return Generator<int, array{0: EmailRecipient, 1: ?EmailSend}>
     */
    private function streamPass(string $campaign, int $chunk, string $type, $query): Generator
    {
        foreach ($query->lazyById($chunk)->chunk($chunk) as $group) {
            $emails = $group->map(fn ($r) => EmailAddress::normalize($r->email));

            $ledger = EmailSend::query()
                ->where('campaign', $campaign)
                ->whereIn('email', $emails->all())
                ->get(['id', 'email', 'status', 'updated_at'])
                ->keyBy('email');

            foreach ($group as $r) {
                $email = EmailAddress::normalize($r->email);

                yield [
                    new EmailRecipient($type, $r->id, $email, (string) $r->sub_token),
                    $ledger->get($email),
                ];
            }
        }
    }

    /**
     * Decide what to do with one recipient and do it: classify against the
     * ledger row + suppression list, then either claim a row for dispatch
     * (returns its id), record a skip, or leave an already-handled/in-flight row
     * alone (returns null). Tallies the outcome into $summary. Writes nothing
     * in dry-run.
     */
    private function prepare(string $campaign, EmailRecipient $r, ?EmailSend $row, bool $dryRun, bool $retryFailed, ?Carbon $staleThreshold, array &$summary): ?int
    {
        $verdict = $this->classify($row, $r->email, $staleThreshold, $retryFailed);

        if ($verdict !== 'dispatch') {
            $summary[$verdict]++;

            if (! $dryRun && ($verdict === 'invalid' || $verdict === 'suppressed')) {
                $this->logSkip($campaign, $r, 'skipped_' . $verdict);
            }

            return null;
        }

        if ($dryRun) {
            $summary['dispatch']++;

            return null;
        }

        $sendId = $this->claim($campaign, $r, $row, $retryFailed, $staleThreshold);

        if ($sendId !== null) {
            $summary['dispatch']++;
        }

        return $sendId;
    }

    /**
     * Classify a candidate without writing anything, using the ledger row
     * preloaded for this chunk (null if none).
     *
     * @return string accepted|queued|failed|invalid|suppressed|dispatch
     */
    private function classify(?EmailSend $row, string $email, ?Carbon $staleThreshold, bool $retryFailed): string
    {
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
     * Atomically claim a row for dispatch. When no ledger row exists (the common
     * first-run case) we go straight to insert — no wasted reclaim UPDATE. When
     * a reclaimable row exists we re-claim it; the WHERE re-checks reclaimability
     * so a concurrent state change can't double-dispatch. Returns the ledger row
     * id, or null if another worker already owns it.
     */
    private function claim(string $campaign, EmailRecipient $r, ?EmailSend $row, bool $retryFailed, ?Carbon $staleThreshold): ?int
    {
        $now = Carbon::now();

        if ($row) {
            $reclaimable = ['skipped_invalid', 'skipped_suppressed'];

            if ($retryFailed) {
                $reclaimable[] = 'failed';
            }

            $updated = EmailSend::query()
                ->where('campaign', $campaign)->where('email', $r->email)
                ->where(function ($q) use ($reclaimable, $staleThreshold) {
                    $q->whereIn('status', $reclaimable);

                    if ($staleThreshold) {
                        $q->orWhere(fn ($q2) => $q2->where('status', 'queued')->where('updated_at', '<', $staleThreshold));
                    }
                })
                ->update([
                    'status' => 'queued',
                    'recipient_type' => $r->type,
                    'recipient_id' => $r->id,
                    'updated_at' => $now,
                ]);

            // Row was no longer reclaimable (e.g. it became accepted meanwhile).
            return $updated > 0 ? $row->id : null;
        }

        $inserted = EmailSend::insertOrIgnore([
            'campaign' => $campaign,
            'email' => $r->email,
            'recipient_type' => $r->type,
            'recipient_id' => $r->id,
            'status' => 'queued',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if (! $inserted) {
            return null; // a concurrent worker inserted it first
        }

        return EmailSend::query()->where('campaign', $campaign)->where('email', $r->email)->value('id');
    }

    private function logSkip(string $campaign, EmailRecipient $r, string $status): void
    {
        EmailSend::query()->updateOrInsert(
            ['campaign' => $campaign, 'email' => $r->email],
            ['recipient_type' => $r->type, 'recipient_id' => $r->id, 'status' => $status, 'updated_at' => Carbon::now()],
        );
    }

    /**
     * Cheap aggregate counts shown before the confirm — no per-recipient
     * classification. The run itself prints the dispatched/skipped tallies.
     */
    private function printPreflight(string $campaign): void
    {
        $userEmails = User::withoutGlobalScopes()->select('email');
        $eligibleSubscribers = Subscriber::whereNotIn('email', $userEmails)->count();

        $this->info("Campaign: {$campaign}");
        $this->line($this->reportLine('Eligible users:', $this->userQuery()->where('emailsub', 1)->count()));
        $this->line($this->reportLine('Eligible subscribers:', $eligibleSubscribers));
        $this->line($this->reportLine('Duplicate skipped:', Subscriber::count() - $eligibleSubscribers));
        $this->line($this->reportLine('Already accepted:', EmailSend::query()->where('campaign', $campaign)->where('status', 'accepted')->count()));
    }

    /**
     * Parsed --retry-stale-queued threshold: null when unset, a Carbon cutoff
     * when valid, or false (after printing an error) when the value is invalid.
     */
    private function staleThreshold(): Carbon|false|null
    {
        $value = $this->option('retry-stale-queued');

        if ($value === null) {
            return null;
        }

        if (! ctype_digit((string) $value) || (int) $value < self::MIN_STALE_SECONDS) {
            $this->error('--retry-stale-queued must be a positive integer of at least ' . self::MIN_STALE_SECONDS
                . ' (seconds). A small value would reclaim genuinely in-flight rows and double-send.');

            return false;
        }

        return Carbon::now()->subSeconds((int) $value);
    }

    /**
     * Lean user query for the bulk send: drops the photosCount global scope and
     * the model's default eager loads ($with), which are pure overhead here.
     */
    private function userQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return User::query()->withoutGlobalScope('photosCount')->setEagerLoads([]);
    }

    private function reportLine(string $label, int $value): string
    {
        return sprintf('%-23s %s', $label, $value);
    }
}
