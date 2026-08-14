<?php

declare(strict_types=1);

namespace App\Console\Commands\tmp\v5\PostMigAug2026;

use App\Enums\XpScore;
use App\Models\Photo;
use App\Services\Metrics\MetricsService;
use App\Services\Redis\RedisKeys;
use App\Services\Tags\GeneratePhotoSummaryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Retires ONE approved litter object key in favour of another.
 *
 * Direction is a per-row product decision (D-4), not inferred: the surviving key may be the
 * legacy one. For entry 1 the canonical `plastic_bag` is retired and the migration-minted
 * `plasticBags` survives, because it holds 10,051 of the 10,304 rows.
 *
 * ORDERING IS LOAD-BEARING. `retired_at` is set and the picker closed BEFORE any data moves.
 * The snapshot is immutable, so a tag created mid-run would not be in it, would survive the
 * retirement, and would fail verification with no remediation short of starting over.
 *
 * Automated (deterministic):
 *   - marks the retired object with retired_at + merged_into_id, closing the tag picker
 *   - creates the surviving object's pivot if it has none
 *   - repoints an immutable, pre-snapshotted set of photo_tags rows
 *   - repoints user_quick_tags off the retired pivot
 *   - regenerates photos.summary and re-runs metrics for processed, non-deleted photos
 *   - deletes the now-dangling retired pivot
 *
 * Manual per tag (gated by status, never automated):
 *   TagsConfig, BrandsConfig, translations, docs/changelog.
 *
 * ClassifyTagsService is never touched — it is the historical record of the v5 migration.
 *
 * @see readme/PostTagMigrationClean.md
 */
class MigrateTag extends Command
{
    protected $signature = 'olm:migrate-tag
        {--entry= : entry_id to operate on}
        {--apply : execute (dry-run by default)}
        {--verify : assert every surface for an applied retirement}
        {--repair-redis : rewrite both objects\' Redis counts from MySQL truth}
        {--advance= : record a manual status transition}
        {--by= : who performed the transition (required with --advance)}
        {--evidence= : verification evidence (required for *_VERIFIED transitions)}
        {--batch=2000 : rows per transaction}
        {--queue= : override the list file (relative to base_path); for rehearsals and tests}';

    protected $description = 'Retire one approved litter object key in favour of another. One entry at a time.';

    private const QUEUE = 'readme/audit/TagRetirements-2026-08.csv';
    private const STATE_DIR = 'migrate-tag';
    private const STATE_VERSION = 4;

    /** Ordered lifecycle. A step may only advance to the next one. */
    public const STATUSES = [
        'IDENTIFIED',
        'MAPPING_APPROVED',
        'CODE_UPDATED',
        'DRY_RUN_VERIFIED',
        'LOCAL_APPLIED',
        'LOCAL_VERIFIED',
        'PRODUCTION_APPLIED',
        'PRODUCTION_VERIFIED',
        'COMPLETE',
    ];

    private const APPLY_REQUIRES = 'DRY_RUN_VERIFIED';

    /** Commands per Redis pipeline round trip. */
    private const REDIS_BATCH = 1000;

    public function handle(GeneratePhotoSummaryService $summaries, MetricsService $metrics): int
    {
        if (!$this->option('entry')) {
            $this->error('--entry is required. The approved entries are listed in ' . self::QUEUE);

            return self::FAILURE;
        }

        $rows = $this->loadQueue();
        $entry = collect($rows)->firstWhere('entry_id', $this->option('entry'));

        if (!$entry) {
            $this->error('Unknown entry: ' . $this->option('entry'));

            return self::FAILURE;
        }

        if ($this->option('advance')) {
            return $this->advance($rows, $entry);
        }

        if ($this->option('verify')) {
            return $this->verify($entry) ? self::SUCCESS : self::FAILURE;
        }

        if ($this->option('repair-redis')) {
            return $this->withLock($entry, fn (): int => $this->repairRedis($entry));
        }

        return $this->option('apply')
            ? $this->apply($entry, $summaries, $metrics)
            : $this->dryRun($entry);
    }

    // ── Lifecycle ────────────────────────────────────────────────────────────

    private function advance(array $rows, array $entry): int
    {
        $to = strtoupper($this->option('advance'));
        $by = $this->option('by');
        $evidence = $this->option('evidence');
        $next = self::STATUSES[array_search($entry['status'], self::STATUSES, true) + 1] ?? null;

        $refusal = match (true) {
            !in_array($to, self::STATUSES, true) => "Unknown status: {$to}. One of: " . implode(', ', self::STATUSES),
            !$by => '--advance requires --by="..." so every transition is attributable.',
            $to !== $next => "Cannot jump {$entry['status']} → {$to}. Next is: " . ($next ?? 'none'),
            str_ends_with($to, '_VERIFIED') && !$evidence => "--advance={$to} requires --evidence=\"...\" recording what was checked.",
            default => null,
        };

        if ($refusal !== null) {
            $this->error($refusal);

            return self::FAILURE;
        }

        // COMPLETE is the one transition the tool can check for itself.
        if ($to === 'COMPLETE' && !$this->verify($entry)) {
            $this->error('  Verification failed — refusing to mark COMPLETE.');

            return self::FAILURE;
        }

        foreach ($rows as &$row) {
            if ($row['entry_id'] === $entry['entry_id']) {
                $row['status'] = $to;

                // approver/approved_at record the MAPPING APPROVAL — the one decision that
                // authorised the entry — so they are written once and never overwritten.
                // Every later rung is attributed in the evidence trail instead; overwriting
                // them here destroyed the approval record on the next advance.
                if (($row['approver'] ?? '') === '') {
                    $row['approver'] = $by;
                    $row['approved_at'] = now()->toDateString();
                }

                $transition = "{$to} by {$by} at " . now()->toDateTimeString();

                if ($evidence) {
                    $transition .= ": {$evidence}";
                }

                $row['verification_evidence'] = trim($row['verification_evidence'] . ' | ' . $transition, ' |');
            }
        }
        unset($row);

        $this->saveQueue($rows);
        $this->info("✓ {$entry['entry_id']} → {$to} (by {$by})");

        return self::SUCCESS;
    }

    // ── Dry run ──────────────────────────────────────────────────────────────

    private function dryRun(array $entry): int
    {
        $this->describe($entry);

        if (!$this->supportedScope($entry) || !$this->survivorIsUsable($entry)) {
            return self::FAILURE;
        }

        $actual = $this->measure($entry);

        $this->line('  Measured now:');
        $this->line("    rows   {$actual['rows']}   (expected {$entry['retired_rows']})");
        $this->line("    items  {$actual['items']}   (expected {$entry['retired_items']})");
        $this->line("    photos {$actual['photos']}  (expected {$entry['retired_photos']})");
        $this->newLine();

        if (!$this->expectationsMatch($entry, $actual)) {
            return self::FAILURE;
        }

        $this->info('Expectations match.');
        $this->line('Next status: ' . (self::STATUSES[array_search($entry['status'], self::STATUSES, true) + 1] ?? 'none'));

        return self::SUCCESS;
    }

    /**
     * Version one retires a key in favour of another in the SAME category, with no type
     * dimension on either side. Category moves and type expansions are refused rather than
     * half-applied, because the update below rewrites only litter_object_id and
     * category_litter_object_id.
     *
     * Note the boundary is asserted against DATA, not against CSV fields, and it is the
     * inverse of the pre-D-4 assumption: the retired side is the one that may hold a pivot and
     * quick tags, and the surviving side is the one that may lack a pivot.
     */
    private function supportedScope(array $entry): bool
    {
        if ((int) $entry['retired_id'] === (int) $entry['desired_id']) {
            $this->error('  Retired and surviving objects are identical — nothing to do.');

            return false;
        }

        $typed = (clone $this->retiredRowQuery($entry))->whereNotNull('litter_object_type_id')->count();

        if ($typed > 0) {
            $this->error("  {$typed} retired rows carry a type. Type expansions are not implemented.");

            return false;
        }

        // Both objects must sit in exactly the one category this entry names. A key tagged in
        // several categories is a different, unimplemented transformation.
        foreach (['retired_id', 'desired_id'] as $field) {
            $categories = DB::table('photo_tags')
                ->where('litter_object_id', (int) $entry[$field])
                ->distinct()
                ->pluck('category_id')
                ->filter()
                ->values();

            if ($categories->count() > 1 || ($categories->count() === 1 && (int) $categories[0] !== (int) $entry['category_id'])) {
                $this->error("  Object {$entry[$field]} is tagged across categories [{$categories->implode(', ')}];"
                    . " this entry only covers category {$entry['category_id']}.");

                return false;
            }
        }

        // XP is weighted by object KEY (XpCalculator resolves summary['keys']['objects'] through
        // XpScore::getObjectXp), so retiring between keys of differing weight silently moves user
        // scores. Refused outright — no override. An accept mechanism belongs here only when a
        // non-equivalent entry is actually scheduled, and it needs its own product decision.
        $retiredXp = XpScore::getObjectXp((string) $entry['retired_key']);
        $desiredXp = XpScore::getObjectXp((string) $entry['desired_key']);

        if ($retiredXp !== $desiredXp) {
            $this->error("  XP weighting differs: {$entry['retired_key']}={$retiredXp}, {$entry['desired_key']}={$desiredXp} per item."
                . ' A retirement must not move user scores.');

            return false;
        }

        return true;
    }

    // ── Apply ────────────────────────────────────────────────────────────────

    private function apply(array $entry, GeneratePhotoSummaryService $summaries, MetricsService $metrics): int
    {
        $statusIdx = array_search($entry['status'], self::STATUSES, true);
        $requiredIdx = array_search(self::APPLY_REQUIRES, self::STATUSES, true);

        if ($statusIdx < $requiredIdx) {
            $this->error("Entry is {$entry['status']}; --apply requires at least " . self::APPLY_REQUIRES . '.');

            return self::FAILURE;
        }

        if (!$this->supportedScope($entry)) {
            return self::FAILURE;
        }

        return $this->withLock($entry, fn (): int => $this->applyLocked($entry, $summaries, $metrics));
    }

    /** Every path that mutates MySQL or Redis for an entry runs under the same exclusive lock. */
    private function withLock(array $entry, callable $work): int
    {
        $lockHandle = $this->acquireLock($entry);

        if ($lockHandle === null) {
            $this->error('Another run holds the lock for this entry.');

            return self::FAILURE;
        }

        try {
            return $work();
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    /** @return resource|null */
    private function acquireLock(array $entry)
    {
        $dir = Storage::disk('local')->path(self::STATE_DIR);

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $handle = fopen($dir . "/{$entry['entry_id']}.lock", 'c');

        if ($handle === false) {
            return null;
        }

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return null;
        }

        return $handle;
    }

    private function applyLocked(array $entry, GeneratePhotoSummaryService $summaries, MetricsService $metrics): int
    {
        $this->describe($entry);

        if (!$this->survivorIsUsable($entry) || !$this->redisIsReachable()) {
            return self::FAILURE;
        }

        $state = $this->loadState($entry);

        if ($state === null) {
            // ── Step 0a: read-only preflight. A mismatch here has mutated nothing at all ──
            if (!$this->expectationsMatch($entry, $this->measure($entry))) {
                return self::FAILURE;
            }

            // ── Step 0b: never continue through somebody else's retirement ──
            if (!$this->retirementIsCompatible($entry)) {
                return self::FAILURE;
            }

            // ── Step 0c: close the door BEFORE anything moves ──
            $closedByThisRun = $this->closePicker($entry);

            // Re-measure under the closed door. Anything that landed between the preflight and
            // the close is caught while a clean reopen is still possible.
            if (!$this->expectationsMatch($entry, $this->measure($entry))) {
                $this->reopenPicker($entry, $closedByThisRun);

                return self::FAILURE;
            }

            $rows = $this->retiredRowQuery($entry)
                ->select('id', 'photo_id', 'quantity')
                ->orderBy('id')
                ->get();

            $photoIds = $rows->pluck('photo_id')->unique()->values()->map('intval')->all();

            $state = [
                'version' => self::STATE_VERSION,
                'entry_id' => $entry['entry_id'],
                'mapping_fingerprint' => $this->mappingFingerprint($entry),
                'baseline_items' => (int) $rows->sum('quantity'),
                'baseline_xp' => (int) DB::table('photos')->whereIn('id', $photoIds)->sum('xp'),
                'retired_object_id' => (int) $entry['retired_id'],
                'category_id' => (int) $entry['category_id'],
                'tag_ids' => $rows->pluck('id')->map('intval')->all(),
                'all_photo_ids' => $photoIds,
                'pending_photo_ids' => $photoIds,
                'quick_tag_ids' => $this->retiredQuickTagIds($entry),
            ];

            // The retirement is only safe to keep once it is recoverable. Without durable state
            // a later run would re-snapshot from scratch against a closed picker.
            try {
                $this->saveState($entry, $state);
            } catch (Throwable $e) {
                $this->error('  Could not persist the snapshot: ' . $e->getMessage());
                $this->reopenPicker($entry, $closedByThisRun);

                return self::FAILURE;
            }

            $this->line('  snapshot: ' . count($state['tag_ids']) . ' tag ids, ' . count($photoIds) . ' photos, '
                . count($state['quick_tag_ids']) . ' quick tags');
        } else {
            if ($state['mapping_fingerprint'] !== $this->mappingFingerprint($entry)) {
                $this->error('  The entry changed since this snapshot was taken. Refusing to resume.');

                return self::FAILURE;
            }

            if (!$this->retirementIsCompatible($entry)) {
                return self::FAILURE;
            }

            // Idempotent: a resume must find the door shut even if the object was reopened by hand.
            $this->closePicker($entry);

            $this->warn('  resuming from snapshot: ' . count($state['tag_ids']) . ' tag ids, '
                . count($state['pending_photo_ids']) . ' summaries outstanding');
        }

        return $this->runRetirement($entry, $state, $summaries, $metrics) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Sets retired_at + merged_into_id. The picker filters on retired_at (LitterObject::active),
     * and both tag-write paths plus the quick-tag sync refuse a retired object, so from this
     * point live traffic cannot add rows the snapshot would miss.
     *
     * @return bool Whether THIS invocation closed it. Only a run that closed the door may reopen it.
     */
    private function closePicker(array $entry): bool
    {
        $updated = DB::table('litter_objects')
            ->where('id', (int) $entry['retired_id'])
            ->whereNull('retired_at')
            ->update([
                'retired_at' => now(),
                'merged_into_id' => (int) $entry['desired_id'],
                'updated_at' => now(),
            ]);

        $this->line($updated > 0
            ? "  picker closed: object {$entry['retired_id']} marked retired → {$entry['desired_id']}"
            : "  picker already closed for object {$entry['retired_id']}");

        return $updated > 0;
    }

    /**
     * Undo a retirement this invocation performed, after a failure that moved nothing. Never
     * called once rows have started moving, and never applied to somebody else's retirement.
     */
    private function reopenPicker(array $entry, bool $closedByThisRun): void
    {
        if (!$closedByThisRun) {
            $this->warn('  picker was closed before this run — leaving it closed.');

            return;
        }

        DB::table('litter_objects')
            ->where('id', (int) $entry['retired_id'])
            ->update(['retired_at' => null, 'merged_into_id' => null, 'updated_at' => now()]);

        $this->line("  picker reopened: object {$entry['retired_id']} is active again, nothing moved.");
    }

    /**
     * An object already retired into a DIFFERENT survivor is another decision's work. Continuing
     * would silently re-point it, so the run refuses instead.
     */
    private function retirementIsCompatible(array $entry): bool
    {
        $object = DB::table('litter_objects')
            ->where('id', (int) $entry['retired_id'])
            ->select('retired_at', 'merged_into_id')
            ->first();

        if ($object === null) {
            $this->error("  object {$entry['retired_id']} does not exist.");

            return false;
        }

        if ($object->retired_at === null) {
            return true;
        }

        $mergedInto = $object->merged_into_id === null ? null : (int) $object->merged_into_id;

        if ($mergedInto !== (int) $entry['desired_id']) {
            $this->error("  object {$entry['retired_id']} is already retired into "
                . ($mergedInto ?? 'nothing') . ", not {$entry['desired_id']}. Refusing.");

            return false;
        }

        return true;
    }

    /**
     * The survivor is where the data lands. A missing object cannot take a pivot at all, and a
     * retired one would receive every moved row behind a closed key. `verify()` asserts this, but
     * only once apply has reported success and cleared its snapshot — so it is asserted here too,
     * ahead of the first mutation, in both the fresh and the resumed path.
     */
    private function survivorIsUsable(array $entry): bool
    {
        $survivor = DB::table('litter_objects')
            ->where('id', (int) $entry['desired_id'])
            ->select('retired_at')
            ->first();

        if ($survivor === null) {
            $this->error("  surviving object {$entry['desired_id']} does not exist.");

            return false;
        }

        if ($survivor->retired_at !== null) {
            $this->error("  surviving object {$entry['desired_id']} is itself retired — refusing to migrate into it.");

            return false;
        }

        return true;
    }

    /**
     * `RedisMetricsCollector::processPhoto()` logs and swallows every Redis error, so a run
     * against a dead Redis repoints MySQL while silently discarding every matching metrics
     * write — and `MetricsService` will not produce those deltas a second time once
     * `processed_fp` has advanced. The end-of-run reconciliation catches it, but only after the
     * picker is closed and the rows have moved. Refuse before touching anything.
     */
    private function redisIsReachable(): bool
    {
        try {
            Redis::ping();

            return true;
        } catch (Throwable $e) {
            $this->error('  Redis is unreachable: ' . $e->getMessage());
            $this->line('  Every metrics write would be swallowed and never replayed. Refusing to start.');

            return false;
        }
    }

    /**
     * The full recovery path after a Redis mismatch failed an apply, in order.
     *
     * `--repair-redis` fixes Redis but does NOT finish the retirement: only a successful
     * `--apply` calls `clearState()`, so a repair-then-verify leaves the snapshot on disk and
     * the entry looking mid-run to the next operator. The rerun is cheap — every MySQL step is
     * idempotent, the rows are already at the target and the pivot is already gone — but it is
     * the only thing that clears the snapshot and reports the retirement finished.
     */
    private function recoverySequence(): void
    {
        $base = 'php artisan olm:migrate-tag --entry=' . $this->option('entry');

        $this->line('  Recover in this order:');
        $this->line("    1. {$base} --repair-redis");
        $this->line("    2. {$base} --apply        (idempotent; this is what clears the snapshot)");
        $this->line("    3. {$base} --verify");
    }

    /** @return array<int, int> */
    private function retiredQuickTagIds(array $entry): array
    {
        $retiredCloId = $this->retiredCloId($entry);

        if ($retiredCloId === null) {
            return [];
        }

        return DB::table('user_quick_tags')
            ->where('clo_id', $retiredCloId)
            ->orderBy('id')
            ->pluck('id')
            ->map('intval')
            ->all();
    }

    private function mappingFingerprint(array $e): string
    {
        return hash('sha256', implode('|', [
            $e['entry_id'],
            (int) $e['category_id'], (int) $e['retired_id'], (int) $e['desired_id'],
            (int) $e['retired_rows'], (int) $e['retired_items'],
        ]));
    }

    private function runRetirement(array $entry, array $state, GeneratePhotoSummaryService $summaries, MetricsService $metrics): bool
    {
        $desiredObjectId = (int) $entry['desired_id'];
        $categoryId = (int) $entry['category_id'];

        $desiredCloId = $this->ensureDesiredPivot($categoryId, $desiredObjectId);

        if ($desiredCloId === null) {
            return false;
        }

        $itemsBefore = (int) $state['baseline_items'];
        $xpBefore = (int) $state['baseline_xp'];

        if (!$this->repointRows($state, $desiredObjectId, $desiredCloId)) {
            return false;
        }

        if (!$this->repointQuickTags($entry, $state, $desiredCloId)) {
            return false;
        }

        $failed = [];
        $processed = 0;

        foreach (array_chunk($state['pending_photo_ids'], 200) as $chunk) {
            foreach (Photo::withTrashed()->whereIn('id', $chunk)->get() as $photo) {
                try {
                    $summaries->run($photo);

                    // deletePhoto() already reversed metrics for a soft-deleted photo;
                    // reprocessing one would re-add them.
                    if ($photo->processed_at !== null && $photo->deleted_at === null) {
                        $metrics->processPhoto($photo);
                    }

                    $processed++;
                } catch (Throwable $e) {
                    $failed[] = $photo->id;
                    $this->warn("    photo {$photo->id}: {$e->getMessage()}");
                }
            }
        }

        $this->line("  summaries + metrics: {$processed} photos");

        $state['pending_photo_ids'] = $failed;
        $this->saveState($entry, $state);

        $ok = true;

        $itemsAfter = (int) DB::table('photo_tags')->whereIn('id', $state['tag_ids'])->sum('quantity');
        $xpAfter = (int) DB::table('photos')->whereIn('id', $state['all_photo_ids'])->sum('xp');

        if ($itemsBefore !== $itemsAfter) {
            $this->error("  ITEMS CHANGED {$itemsBefore} → {$itemsAfter}");
            $ok = false;
        } else {
            $this->line("  items unchanged ✓ ({$itemsAfter})");
        }

        if ($xpBefore !== $xpAfter) {
            $this->error("  XP CHANGED {$xpBefore} → {$xpAfter} — a retirement must not move XP");
            $ok = false;
        } else {
            $this->line("  xp unchanged ✓ ({$xpAfter})");
        }

        $remaining = $this->retiredRowQuery($entry)->count();

        if ($remaining > 0) {
            $this->error("  {$remaining} rows remain on the retired object");
            $ok = false;
        } else {
            $this->line('  retired object drained ✓');
        }

        if ($failed) {
            $this->error('  ' . count($failed) . ' photos failed — retained in state, re-run to retry');

            return false;
        }

        if (!$ok) {
            return false;
        }

        if (!$this->dropRetiredPivot($entry)) {
            return false;
        }

        // The apply is the only invocation holding the snapshot. Reconciling here — rather than
        // leaving it to a later, separate --verify — is what stops a swallowed Redis error from
        // clearing the resumable state and reporting success.
        $retiredId = (int) $entry['retired_id'];

        if (!$this->redisReconciles($desiredObjectId) || !$this->retiredIsDrainedFromRedis($retiredId, $desiredObjectId)) {
            $this->error('  Redis did not reconcile — snapshot retained so the run can be resumed.');
            $this->recoverySequence();

            return false;
        }

        $this->clearState($entry);
        $this->newLine();
        $this->info('Applied. Next status: '
            . (self::STATUSES[array_search($entry['status'], self::STATUSES, true) + 1] ?? 'none'));

        return true;
    }

    /**
     * The surviving object needs a pivot in this category to be selectable. Creating one is
     * safe precisely because the retired object is already excluded by retired_at.
     */
    private function ensureDesiredPivot(int $categoryId, int $desiredObjectId): ?int
    {
        $existing = DB::table('category_litter_object')
            ->where('category_id', $categoryId)
            ->where('litter_object_id', $desiredObjectId)
            ->value('id');

        if ($existing !== null) {
            $this->line("  surviving pivot exists ✓ (CLO {$existing})");

            return (int) $existing;
        }

        $id = DB::table('category_litter_object')->insertGetId([
            'category_id' => $categoryId,
            'litter_object_id' => $desiredObjectId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->line("  created pivot CLO {$id} for ({$categoryId}, {$desiredObjectId})");

        return $id;
    }

    /**
     * Move every snapshotted row to the exact target, tolerating a partially completed run.
     *
     * @param  array<string, mixed>  $state
     */
    private function repointRows(array $state, int $desiredObjectId, int $desiredCloId): bool
    {
        $done = 0;

        foreach (array_chunk($state['tag_ids'], (int) $this->option('batch')) as $chunk) {
            DB::transaction(function () use ($chunk, $state, $desiredObjectId, $desiredCloId, &$done) {
                DB::table('photo_tags')
                    ->whereIn('id', $chunk)
                    ->where('litter_object_id', $state['retired_object_id'])
                    ->where('category_id', $state['category_id'])
                    ->update([
                        'litter_object_id' => $desiredObjectId,
                        'category_litter_object_id' => $desiredCloId,
                    ]);

                $done += DB::table('photo_tags')
                    ->whereIn('id', $chunk)
                    ->where('litter_object_id', $desiredObjectId)
                    ->where('category_litter_object_id', $desiredCloId)
                    ->count();
            });
        }

        $expected = count($state['tag_ids']);

        if ($done !== $expected) {
            $this->error("  {$done} of {$expected} snapshotted rows are at the target.");
            $this->line('  Rows in neither the approved source nor target state were NOT overwritten.');

            return false;
        }

        $this->line("  {$done} rows at object {$desiredObjectId}, CLO {$desiredCloId}");

        return true;
    }

    /**
     * Saved presets point at a CLO. The retired pivot is about to be deleted, so anything
     * referencing it must move or those presets break.
     *
     * Presets are repointed by captured id and never deleted. There is no unique constraint on
     * (user_id, clo_id, type_id) — two presets on the same CLO are legal and can differ by
     * quantity, pickup state, materials, brands, custom name or sort order — so a user already
     * holding a preset on the surviving CLO is not a collision and destroys nothing.
     */
    private function repointQuickTags(array $entry, array $state, int $desiredCloId): bool
    {
        $retiredCloId = $this->retiredCloId($entry);

        if ($retiredCloId === null) {
            $this->line('  quick tags: retired object has no pivot, nothing to move');

            return true;
        }

        $captured = array_map('intval', $state['quick_tag_ids']);

        if ($captured) {
            DB::table('user_quick_tags')
                ->whereIn('id', $captured)
                ->where('clo_id', $retiredCloId)
                ->update(['clo_id' => $desiredCloId, 'updated_at' => now()]);
        }

        // Anything still on the retired pivot arrived after the snapshot was taken. It is
        // outside the captured set but must still move, or the pivot can never be dropped.
        $late = DB::table('user_quick_tags')->where('clo_id', $retiredCloId)->pluck('id')->all();

        if ($late) {
            DB::table('user_quick_tags')
                ->whereIn('id', $late)
                ->update(['clo_id' => $desiredCloId, 'updated_at' => now()]);

            $this->warn('  quick tags: repointed ' . count($late) . ' that arrived after the snapshot');
        }

        // Assert the captured ids specifically. A total count at the target cannot tell a moved
        // preset apart from an unrelated one that was already sitting there.
        $arrived = $captured
            ? DB::table('user_quick_tags')->whereIn('id', $captured)->where('clo_id', $desiredCloId)->count()
            : 0;

        if ($arrived !== count($captured)) {
            $this->error('  quick tags: ' . $arrived . ' of ' . count($captured) . ' captured presets reached CLO ' . $desiredCloId);

            return false;
        }

        $this->line('  quick tags: ' . (count($captured) + count($late)) . " now at CLO {$desiredCloId} (none deleted)");

        return true;
    }

    /**
     * A reference that survives the move is a failure, not a warning: the pivot cannot be
     * dropped, so the retirement is incomplete and must stay resumable.
     */
    private function dropRetiredPivot(array $entry): bool
    {
        $retiredCloId = $this->retiredCloId($entry);

        if ($retiredCloId === null) {
            return true;
        }

        // `photo_tags.category_litter_object_id` is ON DELETE CASCADE, so a row inserted between
        // the count and the delete would be destroyed by the delete rather than blocking it.
        // Taking the pivot row's write lock first closes that window: InnoDB needs a shared lock
        // on the parent row to check the foreign key, so no INSERT referencing this pivot can
        // commit while the lock is held.
        return DB::transaction(function () use ($retiredCloId): bool {
            DB::table('category_litter_object')->where('id', $retiredCloId)->lockForUpdate()->first();

            $photoTags = DB::table('photo_tags')->where('category_litter_object_id', $retiredCloId)->count();
            $quickTags = DB::table('user_quick_tags')->where('clo_id', $retiredCloId)->count();

            if ($photoTags + $quickTags > 0) {
                $this->error("  retired pivot CLO {$retiredCloId} still has {$photoTags} photo tags and "
                    . "{$quickTags} quick tags — cannot complete the retirement");

                return false;
            }

            DB::table('category_litter_object')->where('id', $retiredCloId)->delete();
            $this->line("  removed dangling pivot CLO {$retiredCloId}");

            return true;
        });
    }

    /**
     * Draining MySQL removes the retired object from every MySQL-derived expectation, so
     * `redisReconciles` alone stops looking at the scopes it used to occupy. Every one of those
     * scopes is now a scope the SURVIVING object touches, so the survivor's population
     * enumerates exactly where the retired object must read zero.
     */
    private function retiredIsDrainedFromRedis(int $retiredId, int $desiredId): bool
    {
        $reads = [];

        foreach ($this->locationScopes($desiredId) as [$kind, $key, $items]) {
            $reads[] = [$kind, $key, (string) $retiredId];
        }

        foreach ($this->itemsGroupedBy($desiredId, 'p.user_id') as $userId => $items) {
            $reads[] = ['hash', RedisKeys::user((int) $userId) . ':tags', 'obj:' . $retiredId];
        }

        try {
            $values = $this->readCounts($reads);
        } catch (Throwable $e) {
            $this->error('  Redis unreadable: ' . $e->getMessage());

            return false;
        }

        $stale = [];

        foreach ($reads as $i => [$kind, $key, $field]) {
            if ($values[$i] !== 0) {
                $stale[] = "      {$key} {$field}: {$values[$i]}, expected 0";
            }
        }

        if ($stale !== []) {
            $this->error(sprintf('    object %d still present in %d of %d scopes', $retiredId, count($stale), count($reads)));

            foreach (array_slice($stale, 0, 10) as $line) {
                $this->line($line);
            }

            return false;
        }

        $this->line(sprintf('      object %d reads zero across %d scopes', $retiredId, count($reads)));

        return true;
    }

    /**
     * Every location scope an object's photos touch, as [kind, key, items] triples.
     *
     * `RedisMetricsCollector::updateTags()` writes each count to BOTH a `:obj` hash and a
     * `rank:objects` ZSET, so both are enumerated — `LocationService::getTopTags()` reads the
     * ZSET as its fast path and only falls back to the hash, so a hash-only reconciliation
     * passes while the surface users actually see is wrong.
     *
     * User tag hashes are enumerated by the callers instead: `updateUserMetrics()` writes an
     * `obj:{id}` hash field with no ranking counterpart.
     *
     * @return array<int, array{0: string, 1: string, 2: int}>
     */
    private function locationScopes(int $objectId): array
    {
        $reads = [];

        foreach ($this->scopeItems($objectId) as $scope => $items) {
            $reads[] = ['hash', RedisKeys::objects($scope), $items];
            $reads[] = ['zset', RedisKeys::ranking($scope, 'objects'), $items];
        }

        return $reads;
    }

    /**
     * MySQL truth per location scope: global, plus every country / state / city holding one of
     * the object's processed, live photos.
     *
     * @return array<string, int> scope prefix => items
     */
    private function scopeItems(int $objectId): array
    {
        $items = [RedisKeys::global() => $this->itemsFor($objectId)];

        $levels = [
            'country_id' => fn (int $id): string => RedisKeys::country($id),
            'state_id' => fn (int $id): string => RedisKeys::state($id),
            'city_id' => fn (int $id): string => RedisKeys::city($id),
        ];

        foreach ($levels as $column => $keyFor) {
            foreach ($this->itemsGroupedBy($objectId, "p.{$column}") as $scopeId => $count) {
                $items[$keyFor((int) $scopeId)] = $count;
            }
        }

        return $items;
    }

    /**
     * A missing hash field and a missing ZSET member both read as zero — the state a fully
     * drained object is expected to be in. Scores are floats in Redis, but every count written
     * through `zIncrBy` is a whole number, so the cast is exact.
     *
     * Pipelined. A retirement touches ~1,200 location scopes plus one hash per contributing
     * user, and each reconciliation reads every one of them for both objects; issued singly
     * that was thousands of sequential round trips per `--verify`.
     *
     * @param array<int, array{0: string, 1: string, 2: string}> $reads kind, key, field
     *
     * @return array<int, int> counts, in the order given
     */
    private function readCounts(array $reads): array
    {
        $counts = [];

        foreach (array_chunk($reads, self::REDIS_BATCH) as $chunk) {
            $replies = Redis::pipeline(function ($pipe) use ($chunk) {
                foreach ($chunk as [$kind, $key, $field]) {
                    if ($kind === 'zset') {
                        $pipe->zscore($key, $field);
                    } else {
                        $pipe->hget($key, $field);
                    }
                }
            });

            foreach (array_keys($chunk) as $i) {
                $counts[] = (int) ($replies[$i] ?? 0);
            }
        }

        return $counts;
    }

    // ── Repair ───────────────────────────────────────────────────────────────

    /**
     * Rewrite both objects' Redis counts from MySQL truth, absolutely.
     *
     * A retirement's entire Redis footprint is the objects dimension. `supportedScope()` holds
     * the category, the quantities and the XP weighting equal, so the litter and XP deltas are
     * zero and no stats hash, HLL or leaderboard ZSET is written at all. Everything a swallowed
     * Redis error can lose is therefore recomputable from MySQL here — which is what makes this
     * a bounded repair rather than the global `olm:redis:rebuild`, which §8a of the design doc
     * records as lossy for litter and unsafe to run on production.
     *
     * Absolute, never a delta: once MySQL and `processed_tags` are updated `MetricsService`
     * produces no second delta, so a replay-based repair would have nothing to replay.
     */
    private function repairRedis(array $entry): int
    {
        $retiredId = (int) $entry['retired_id'];
        $desiredId = (int) $entry['desired_id'];

        $this->describe($entry);

        if (!$this->redisIsReachable()) {
            return self::FAILURE;
        }

        $survivorScopes = $this->scopeItems($desiredId);
        $retiredScopes = $this->scopeItems($retiredId);

        // The union of both populations. A fully drained object touches no scope of its own, so
        // the survivor's population is what enumerates where the retired id must now read zero;
        // before the drain completes, the retired object's own scopes still matter.
        $scopes = array_unique(array_merge(array_keys($survivorScopes), array_keys($retiredScopes)));

        $survivorUsers = $this->itemsGroupedBy($desiredId, 'p.user_id');
        $retiredUsers = $this->itemsGroupedBy($retiredId, 'p.user_id');
        $users = array_unique(array_merge(array_keys($survivorUsers), array_keys($retiredUsers)));

        $writes = [];

        foreach ($scopes as $scope) {
            $hash = RedisKeys::objects($scope);
            $rank = RedisKeys::ranking($scope, 'objects');

            $writes[] = ['hash', $hash, (string) $desiredId, $survivorScopes[$scope] ?? 0];
            $writes[] = ['zset', $rank, (string) $desiredId, $survivorScopes[$scope] ?? 0];
            $writes[] = ['hash', $hash, (string) $retiredId, $retiredScopes[$scope] ?? 0];
            $writes[] = ['zset', $rank, (string) $retiredId, $retiredScopes[$scope] ?? 0];
        }

        foreach ($users as $userId) {
            $key = RedisKeys::user((int) $userId) . ':tags';

            $writes[] = ['hash', $key, 'obj:' . $desiredId, $survivorUsers[$userId] ?? 0];
            $writes[] = ['hash', $key, 'obj:' . $retiredId, $retiredUsers[$userId] ?? 0];
        }

        try {
            $this->writeCounts($writes);
        } catch (Throwable $e) {
            $this->error('  Repair failed against Redis: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->line(sprintf('  rewrote %d location scopes and %d user hashes from MySQL', count($scopes), count($users)));

        if (!$this->redisReconciles($desiredId) || !$this->retiredIsDrainedFromRedis($retiredId, $desiredId)) {
            return self::FAILURE;
        }

        // A repair is never the last step of a failed apply. The snapshot on disk is the signal
        // that one is outstanding, and only a successful --apply clears it.
        $base = 'php artisan olm:migrate-tag --entry=' . $this->option('entry');

        $this->newLine();
        $this->info(Storage::disk('local')->exists($this->statePath($entry))
            ? "Redis repaired. A snapshot is still outstanding — finish with:\n  {$base} --apply\n  {$base} --verify"
            : "Redis repaired. No snapshot outstanding — confirm with:\n  {$base} --verify");

        return self::SUCCESS;
    }

    /**
     * Zero is written as ABSENCE, not as a stored zero. A zero-scored member is still a member
     * of the ranking ZSET `LocationService::getTopTags()` reads, and in a scope holding fewer
     * objects than the page size a retired key would still be served.
     *
     * Pipelined for the same reason as `readCounts()` — a repair rewrites both objects at every
     * scope and user hash, which is thousands of writes.
     *
     * @param array<int, array{0: string, 1: string, 2: string, 3: int}> $writes kind, key, field, items
     */
    private function writeCounts(array $writes): void
    {
        foreach (array_chunk($writes, self::REDIS_BATCH) as $chunk) {
            Redis::pipeline(function ($pipe) use ($chunk) {
                foreach ($chunk as [$kind, $key, $field, $items]) {
                    if ($items === 0) {
                        $kind === 'zset' ? $pipe->zrem($key, $field) : $pipe->hdel($key, $field);
                    } elseif ($kind === 'zset') {
                        $pipe->zadd($key, $items, $field);
                    } else {
                        $pipe->hset($key, $field, $items);
                    }
                }
            });
        }
    }

    private function retiredCloId(array $entry): ?int
    {
        $id = DB::table('category_litter_object')
            ->where('category_id', (int) $entry['category_id'])
            ->where('litter_object_id', (int) $entry['retired_id'])
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    // ── Verify ───────────────────────────────────────────────────────────────

    /**
     * Asserts every surface in §6 of the design doc. Required before COMPLETE.
     */
    private function verify(array $entry): bool
    {
        $this->newLine();
        $this->line("<options=bold>verify {$entry['entry_id']}</>");

        $retiredId = (int) $entry['retired_id'];
        $desiredId = (int) $entry['desired_id'];
        $ok = true;

        $checks = [];

        $checks['photo_tags drained'] = DB::table('photo_tags')->where('litter_object_id', $retiredId)->count() === 0;

        $desiredCloId = DB::table('category_litter_object')
            ->where('category_id', (int) $entry['category_id'])
            ->where('litter_object_id', $desiredId)
            ->value('id');

        $checks['surviving pivot exists'] = $desiredCloId !== null;

        $object = DB::table('litter_objects')->where('id', $retiredId)
            ->select('retired_at', 'merged_into_id')->first();

        $checks['retired object is marked retired'] = $object !== null && $object->retired_at !== null;
        $checks['retirement points at the survivor'] = $object !== null && (int) $object->merged_into_id === $desiredId;
        $checks['surviving object is still active'] = DB::table('litter_objects')
            ->where('id', $desiredId)->whereNull('retired_at')->exists();

        $retiredCloId = $this->retiredCloId($entry);

        $checks['retired pivot is gone'] = $retiredCloId === null;
        $checks['nothing references the retired pivot'] = $retiredCloId === null
            || DB::table('photo_tags')->where('category_litter_object_id', $retiredCloId)->count()
                + DB::table('user_quick_tags')->where('clo_id', $retiredCloId)->count() === 0;

        // A floor, NOT proof of preservation — an unrelated preset already on the surviving CLO
        // satisfies it just as well as a moved one. By-id preservation is asserted during the
        // apply, which is the only invocation that holds the captured set.
        $checks['quick tags at or above the recorded floor'] = DB::table('user_quick_tags')
            ->where('clo_id', $desiredCloId)
            ->count() >= (int) $entry['quick_tags_on_retired_clo'];

        $checks['no summary references retired object'] = $this->summariesReferencing($retiredId) === 0;

        $checks['redis reconciles'] = $this->redisReconciles($desiredId)
            && $this->retiredIsDrainedFromRedis($retiredId, $desiredId);

        foreach ($checks as $label => $passed) {
            $this->line(sprintf('    %-38s %s', $label, $passed ? '✓' : '✗ FAILED'));
            $ok = $ok && $passed;
        }

        $this->newLine();
        $ok ? $this->info('  All surfaces verified.') : $this->error('  Verification FAILED.');

        return $ok;
    }

    private function summariesReferencing(int $objectId): int
    {
        $count = 0;

        // `summary` is TEXT, not a JSON column, so the decode stays in PHP — a malformed row
        // must not break the gate. The LIKE narrows the candidates; chunkById keeps the scan
        // off OFFSET pagination, which would otherwise re-walk the table once per page.
        DB::table('photos')
            ->whereNotNull('summary')
            ->where('summary', 'LIKE', '%"' . $objectId . '":%')
            ->select('id', 'summary')
            ->chunkById(2000, function ($photos) use ($objectId, &$count) {
                foreach ($photos as $photo) {
                    $summary = json_decode($photo->summary, true);

                    if (is_array($summary) && isset($summary['keys']['objects'][$objectId])) {
                        $count++;
                    }
                }
            });

        return $count;
    }

    /**
     * Absolute reconciliation, MySQL vs Redis, at EVERY scope the object touches: global, each
     * country/state/city that holds one of its photos, and each contributing user's tag hash.
     *
     * RedisMetricsCollector::updateTags() writes an object count for every scope in
     * RedisKeys::getPhotoScopes() plus an obj:{id} field per contributing user, so a global-only
     * check — the shape this carried until the harness was folded in — covered a small fraction
     * of what a retirement moves.
     *
     * Reconciliation is absolute, never a before/after delta: once MySQL and processed_tags are
     * updated MetricsService produces no second delta, so a delta-based rerun after a Redis
     * outage would fail forever.
     */
    private function redisReconciles(int $objectId): bool
    {
        $expected = [];

        foreach ($this->locationScopes($objectId) as [$kind, $key, $items]) {
            $expected[] = [$kind, $key, (string) $objectId, $items];
        }

        foreach ($this->itemsGroupedBy($objectId, 'p.user_id') as $userId => $items) {
            $expected[] = ['hash', RedisKeys::user((int) $userId) . ':tags', 'obj:' . $objectId, $items];
        }

        try {
            $values = $this->readCounts($expected);
        } catch (Throwable $e) {
            $this->error('  Redis unreadable: ' . $e->getMessage());

            return false;
        }

        $mismatches = [];

        foreach ($expected as $i => [$kind, $key, $field, $mysql]) {
            if ($mysql !== $values[$i]) {
                $mismatches[] = "      {$key} {$field}: MySQL {$mysql} vs Redis {$values[$i]}";
            }
        }

        if ($mismatches !== []) {
            $this->error(sprintf('    object %d: %d of %d scopes disagree', $objectId, count($mismatches), count($expected)));

            foreach (array_slice($mismatches, 0, 10) as $line) {
                $this->line($line);
            }

            // NOT olm:redis:rebuild — §8a records it as lossy for litter and unsafe on
            // production. --repair-redis rewrites only this entry's two objects, from MySQL.
            $this->line('    Repair with: php artisan olm:migrate-tag --entry='
                . $this->option('entry') . ' --repair-redis');

            return false;
        }

        $this->line(sprintf('      object %d reconciled across %d scopes', $objectId, count($expected)));

        return true;
    }

    /**
     * Items on an object across processed, live photos — the population MetricsService counts,
     * and the single definition of "MySQL truth" used by every reconciliation below.
     */
    private function itemsQuery(int $objectId): \Illuminate\Database\Query\Builder
    {
        return DB::table('photo_tags as pt')
            ->join('photos as p', 'p.id', '=', 'pt.photo_id')
            ->where('pt.litter_object_id', $objectId)
            ->whereNotNull('p.processed_at')
            ->whereNull('p.deleted_at');
    }

    private function itemsFor(int $objectId): int
    {
        return (int) $this->itemsQuery($objectId)->sum('pt.quantity');
    }

    /**
     * @return array<int, int> scope id => items
     *
     * Built from get(), not pluck(). Query\Builder::pluck() re-selects only the two columns it
     * is handed, discarding the selectRaw aliases above, and on a grouped query that returns
     * wrong numbers rather than an error — measured 0 where the real value was 951.
     */
    private function itemsGroupedBy(int $objectId, string $column): array
    {
        $rows = $this->itemsQuery($objectId)
            ->whereNotNull($column)
            ->selectRaw("{$column} AS scope_id, SUM(pt.quantity) AS items")
            ->groupBy($column)
            ->get();

        $map = [];

        foreach ($rows as $row) {
            $map[(int) $row->scope_id] = (int) $row->items;
        }

        return $map;
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function describe(array $e): void
    {
        $this->newLine();
        $this->line("<options=bold>{$e['entry_id']}</>   status: {$e['status']}   class: {$e['class']}");
        $this->line("  retire {$e['category_key']}/{$e['retired_key']} (object {$e['retired_id']})");
        $this->line("  keep   {$e['category_key']}/{$e['desired_key']} (object {$e['desired_id']})");
        $this->newLine();
    }

    private function retiredRowQuery(array $e)
    {
        return DB::table('photo_tags')
            ->where('category_id', (int) $e['category_id'])
            ->where('litter_object_id', (int) $e['retired_id']);
    }

    /** @return array{rows:int, items:int, photos:int} */
    private function measure(array $e): array
    {
        $row = $this->retiredRowQuery($e)
            ->selectRaw('COUNT(*) AS n_rows, COALESCE(SUM(quantity), 0) AS n_items, COUNT(DISTINCT photo_id) AS n_photos')
            ->first();

        return [
            'rows' => (int) $row->n_rows,
            'items' => (int) $row->n_items,
            'photos' => (int) $row->n_photos,
        ];
    }

    private function expectationsMatch(array $entry, array $actual): bool
    {
        foreach (['rows' => 'retired_rows', 'items' => 'retired_items', 'photos' => 'retired_photos'] as $field => $column) {
            $expected = (int) $entry[$column];

            if ($expected !== $actual[$field]) {
                $this->error("  {$field}: expected {$expected}, found {$actual[$field]} — data moved since approval. Aborting.");

                return false;
            }
        }

        return true;
    }

    // ── State ────────────────────────────────────────────────────────────────

    private function statePath(array $e): string
    {
        return self::STATE_DIR . "/{$e['entry_id']}.json";
    }

    private function loadState(array $e): ?array
    {
        $disk = Storage::disk('local');
        $path = $this->statePath($e);

        if (!$disk->exists($path)) {
            return null;
        }

        try {
            $state = json_decode($disk->get($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $ex) {
            throw new \RuntimeException("Corrupt migration state at {$path}: {$ex->getMessage()}");
        }

        foreach (['version', 'entry_id', 'mapping_fingerprint', 'tag_ids', 'all_photo_ids', 'pending_photo_ids',
            'baseline_items', 'baseline_xp', 'retired_object_id', 'category_id', 'quick_tag_ids'] as $f) {
            if (!array_key_exists($f, $state)) {
                throw new \RuntimeException("Migration state at {$path} is missing '{$f}'.");
            }
        }

        if ((int) $state['version'] !== self::STATE_VERSION || $state['entry_id'] !== $e['entry_id']) {
            throw new \RuntimeException("Migration state at {$path} does not match this entry.");
        }

        return $state;
    }

    private function saveState(array $e, array $state): void
    {
        $disk = Storage::disk('local');
        $tmp = $this->statePath($e) . '.tmp';

        $disk->put($tmp, json_encode($state, JSON_THROW_ON_ERROR));

        $from = $disk->path($tmp);
        $to = $disk->path($this->statePath($e));

        if (!@rename($from, $to)) {
            @unlink($from);

            throw new \RuntimeException("Could not atomically write migration state to {$to}");
        }
    }

    private function clearState(array $e): void
    {
        Storage::disk('local')->delete($this->statePath($e));
    }

    // ── CSV ──────────────────────────────────────────────────────────────────

    private function queuePath(): string
    {
        return $this->option('queue') ?: self::QUEUE;
    }

    /** @return array<int, array<string, string>> */
    private function loadQueue(): array
    {
        $fh = fopen(base_path($this->queuePath()), 'r');
        $header = fgetcsv($fh);
        $rows = [];

        while (($line = fgetcsv($fh)) !== false) {
            if (count($line) === count($header)) {
                $rows[] = array_combine($header, $line);
            }
        }

        fclose($fh);

        return $rows;
    }

    /** @param array<int, array<string, string>> $rows */
    private function saveQueue(array $rows): void
    {
        $path = base_path($this->queuePath());
        $tmp = $path . '.tmp';

        $fh = fopen($tmp, 'w');
        fputcsv($fh, array_keys($rows[0]));

        foreach ($rows as $row) {
            fputcsv($fh, array_values($row));
        }

        fclose($fh);
        rename($tmp, $path);
    }
}
