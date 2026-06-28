# Email System

Deliverability-focused email subsystem for OpenLitterMap: bulk campaign sends (the "Update N" mailshot), a resumable send ledger, and an inbound bounce/complaint feedback loop that keeps the suppression list current.

Two halves:

- **Outbound** — `olm:send-email-to-subscribed` streams recipients, guards each address, and dispatches one `DispatchEmail` job per recipient. Every recipient is recorded in the `email_sends` ledger so a send is interruptible, resumable, and auditable.
- **Inbound** — AWS SES posts bounce/complaint notifications via SNS to a webhook, which writes to `email_suppressions`. A backfill command imports the SES account-level suppression list for addresses that died before the webhook existed. The suppression list is the source of truth the outbound guard checks.

> Phase 1 scope. Email **verification** (`users.email_verified_at` + an `email:backfill-verified-at` command) was deliberately split out as Phase 2 and is **not** part of this system yet. See [Phase 2](#phase-2-deferred).

Related docs: [API.md](API.md) (`Webhooks & Mailing List` section — request/response contracts) and [ArtisanCommands.md](ArtisanCommands.md) (command switches + the production runbook).

## Data Model

| Table | Role | Key |
|-------|------|-----|
| `email_sends` | Campaign ledger — one row per (campaign, email) | `uniq(campaign, email)` is the atomic claim lock |
| `email_suppressions` | Source of truth for undeliverable addresses | `uniq(email)` — one row per normalized email |
| `email_events` | Audit + idempotency record of raw SNS bounce/complaint events | `uniq(sns_id, email)` |
| `subscribers` | Standalone mailing-list signups (no account) | `email` indexed; `sub_token` for unsubscribe |
| `users.emailsub` | Per-user opt-in flag (`integer`, default `1`) | Filtered in the send; cleared on unsubscribe |

### `email_sends` (the ledger)

`status` drives all resume/retry behaviour:

| Status | Meaning |
|--------|---------|
| `queued` | Claimed and a `DispatchEmail` job is in flight |
| `accepted` | Provider accepted the message (written in the job's `handle()`) |
| `failed` | Send exhausted its retry window (written only in the job's `failed()`) |
| `skipped_invalid` | Address fails `EmailAddress::isSendable()` |
| `skipped_suppressed` | Address is in `email_suppressions` |

Re-running a campaign skips `accepted` and in-flight `queued` rows and **re-evaluates** previously-skipped rows — an address that becomes sendable (un-suppressed, or fixed) sends on a later run. The `uniq(campaign, email)` constraint is the lock: concurrent workers can't double-claim or double-send.

### `email_suppressions` (source of truth)

One row per normalized email. `reason` ∈ {`bounced`, `complained`, `manual`}, `source` ∈ {`ses`, `backfill`, `manual`}. **Complaint outranks bounce and is never downgraded** — enforced in `EmailSuppression::suppress()`, which upserts by email and parses the raw event timestamp leniently.

Suppression belongs to an **email address, not a user/subscriber row** — this is the design's first principle. It covers users and subscribers alike from one keyspace and persists independently of accounts (a re-subscribe or a deleted account doesn't lose the suppression). Do **not** denormalize it into a per-row flag on `users`/`subscribers`.

### `email_events` (audit / idempotency)

Raw verified SNS payloads for `Bounce`/`Complaint` recipients live here only. `firstOrCreate` on `(sns_id, email)` makes duplicate SNS deliveries harmless. Insert-only (`const UPDATED_AT = null`).

## Core Support

- **`App\Support\EmailAddress`** — the single source of truth for two address operations:
  - `normalize($email)` → `strtolower(trim($email))`. The canonical key used everywhere (`email_sends`, `email_suppressions`, dispatch-guard lookups). No Gmail dot/plus canonicalisation.
  - `isSendable($email)` → `filter_var(..., FILTER_VALIDATE_EMAIL)`. The send-time deliverability guard. `filter_var` rejects single-label-domain addresses like `Estherbarriga@8` (the address that broke the Update 28 send); Laravel's plain `email` rule is RFC-permissive and lets them through. The `/subscribe` endpoint applies the same underlying check via the `email:filter` validation rule.
- **`App\Support\EmailRecipient`** — value object (`type`, `id`, `email`, `sub_token`) so the mail job never assumes a `User`. Users and subscribers each carry their own `sub_token` for the unsubscribe link; the property name matches what the `EmailUpdate` view reads.

## Outbound: Campaign Send

`App\Console\Commands\SendEmailToSubscribed` (`olm:send-email-to-subscribed`). Switches and the production runbook are documented in [ArtisanCommands.md](ArtisanCommands.md); the architecture:

**Recipient selection** (`recipients()` → two passes, streamed via `lazyById`/`chunkById` keyset paging):
1. Users where `emailsub = 1` (includes unverified accounts). The user query drops the `photosCount` global scope and the model's `$with` eager loads and selects only `id, email, sub_token` — pure overhead for a bulk send.
2. Subscribers whose email is **not** a user email (user wins; the subscriber is counted as a duplicate and skipped).

Each chunk batch-loads its ledger rows in one `whereIn` query (no N+1). The suppression list is loaded **once** into an in-memory set at the start of the run and checked with an O(1) `isset()` in `classify()` — this is intentional: the set is small (hard bounces/complaints only), and the alternative (a query-time `whereNotExists`) would drop the `skipped_suppressed` audit row and zero the operator-facing "Suppressed skipped" tally.

**Per-recipient flow** (`prepare()` → `classify()` → `claim()`):
- Already `accepted` or in-flight `queued` → leave alone.
- Fails `isSendable()` → log `skipped_invalid`.
- In the suppression set → log `skipped_suppressed`.
- Otherwise → atomically claim the ledger row (`insertOrIgnore` on first run, a reclaim `UPDATE` for previously-skipped/`--retry` rows) and dispatch one `DispatchEmail`.

**Recovery flags:** `--retry-failed` re-claims `failed` rows; `--retry-stale-queued=N` re-claims `queued` rows older than N seconds (crash recovery; rejects `N < 60` to avoid reclaiming genuinely in-flight rows and double-sending). `--dry-run` writes/dispatches nothing. `--only-email=a@b.com` sends a single untracked preview (no ledger row).

**`DispatchEmail` job** (`App\Jobs\Emails\DispatchEmail`): one job per recipient — the blast radius of a bad address is exactly one job. Throttled by `RateLimited('ses-emails')`. Attempts are **time-based** (`retryUntil = now()+6h`) not a fixed `$tries`, because a throttle release increments the attempt count — a fixed `$tries` would burn attempts and strand valid recipients as `failed` during a bulk run. Genuine failures are bounded by `$maxExceptions = 3` (`backoff = [10, 60, 300]`). Ledger writes happen in the **terminal hooks**: `accepted` (+ `provider_message_id`) in `handle()`, `failed` (+ truncated reason) only in `failed()` — never on the first throw. The mailable is `App\Mail\EmailUpdate`.

## Inbound: Suppression Feedback Loop

### SNS webhook — `POST /webhooks/aws/ses/sns`

`SesSnsWebhookController` (CSRF-exempt; reads the raw `text/plain` body since SNS doesn't post form data). Order of checks, all before any processing:
1. Parse the SNS envelope (`400` on garbage).
2. Verify the SNS signature via `aws/aws-php-sns-message-validator` (`403` on failure). The validator is container-injected so tests can swap it.
3. Assert `TopicArn === config('services.ses.topic_arn')` (`403` on mismatch).

Then by type:
- `SubscriptionConfirmation` → GET the `SubscribeURL`; if that fails, return `502` so SNS retries rather than leave the subscription silently pending.
- `Notification` → decode the SES payload and route:
  - **Bounce**: `Transient` → ignored (soft); `Permanent` → `suppress(..., 'bounced', 'ses')`; `Undetermined` → recorded as an event but **not** suppressed.
  - **Complaint** → always `suppress(..., 'complained', 'ses')`.

Every handled recipient is recorded in `email_events` (idempotent on `(sns_id, email)`). Always returns `200` for handled/ignored events so SNS stops retrying.

**Prod requirement:** `SES_SNS_TOPIC_ARN` must be set (`eu-west-1` in prod). `.env.example` ships it empty, and an unset/mismatched value makes the webhook reject every message.

### Backfill import — `email:import-suppressions {file}`

`ImportEmailSuppressions` catches addresses that hard-failed before the webhook existed. Produce the input with:

```bash
aws sesv2 list-suppressed-destinations --region eu-west-1 --page-size 1000 --output json > ses-suppressed.json
```

Reads `SuppressedDestinationSummaries[]`, maps `BOUNCE → bounced` / `COMPLAINT → complained`, upserts with `source = backfill`, preserves complaint-over-bounce, idempotent on re-run.

## Mailing List: Subscribe & Unsubscribe

- **Subscribe** — `POST /subscribe` (`SubscribersController`, public, unauthenticated). The live `Footer.vue` form posts here. Validation: `required | max:100 | email:filter | unique:subscribers`. A plain newsletter signup that creates a `subscribers` row and does **not** touch user accounts. A `sub_token` (30 random chars) is generated on create.
- **Unsubscribe** — `GET /emails/unsubscribe/{token}` (`EmailSubController@unsubEmail`). Campaign emails link here with the recipient's `sub_token`. A matching **user** is set `emailsub = 0`; a matching **subscriber** row is deleted. Redirects to `/?unsub=1`. (`GET /unsubscribe/{token}` → `UsersController` is a legacy alias.)

## Phase 2 (deferred)

Email **verification** — a `users.email_verified_at` column and an `email:backfill-verified-at` command — was split out of Phase 1 as unrelated to the mass-email deliverability fix. It does not exist on this branch. Do not add a `backfill-verified-at` step to the production runbook.

## Testing

`tests/Feature/Email/` covers the subsystem:

| File | Covers |
|------|--------|
| `EmailValidationTest` | `EmailAddress::isSendable()` / `normalize()` |
| `EmailSendLedgerTest` | Ledger lifecycle, claim/skip/resume, suppression and duplicate guards |
| `DispatchEmailTest` | Job accepted/failed ledger writes, retry behaviour |
| `EmailWebhookTest` | SNS signature/topic checks, bounce/complaint → suppression, idempotency |
| `EmailSuppressionImportTest` | Backfill import mapping + idempotency |
| `EmailUpdateTest` | End-to-end send + report counts |

Plus `tests/Feature/EmailSubscriptionTest.php` for `/subscribe` validation. Note `EmailSendLedgerTest::test_suppressed_address_logged_skipped_suppressed_not_dispatched` asserts the `skipped_suppressed` audit row — a reason the in-memory suppression check is kept over a query-time exclusion.
