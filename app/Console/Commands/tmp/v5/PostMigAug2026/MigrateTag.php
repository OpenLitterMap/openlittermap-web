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
        {--advance= : record a manual status transition}
        {--by= : who performed the transition (required with --advance)}
        {--evidence= : verification evidence (required for *_VERIFIED transitions)}
        {--batch=2000 : rows per transaction}
        {--queue= : override the list file (relative to base_path); for rehearsals and tests}';

    protected $description = 'Retire one approved litter object key in favour of another. One entry at a time.';

    private const QUEUE = 'readme/audit/TagRetirements-2026-08.csv';
    private const STATE_DIR = 'migrate-tag';
    private const STATE_VERSION = 3;

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
                $row['approver'] = $by;
                $row['approved_at'] = now()->toDateString();

                if ($evidence) {
                    $row['verification_evidence'] = trim($row['verification_evidence'] . ' | ' . $evidence, ' |');
                }
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

        if (!$this->supportedScope($entry)) {
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

        $lockHandle = $this->acquireLock($entry);

        if ($lockHandle === null) {
            $this->error('Another run holds the lock for this entry.');

            return self::FAILURE;
        }

        try {
            return $this->applyLocked($entry, $summaries, $metrics);
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

        // ── Step 0: close the door BEFORE anything moves ──
        $this->closePicker($entry);

        $state = $this->loadState($entry);

        if ($state === null) {
            $rows = $this->retiredRowQuery($entry)
                ->select('id', 'photo_id', 'quantity')
                ->orderBy('id')
                ->get();

            $actual = [
                'rows' => $rows->count(),
                'items' => (int) $rows->sum('quantity'),
                'photos' => $rows->pluck('photo_id')->unique()->count(),
            ];

            if (!$this->expectationsMatch($entry, $actual)) {
                return self::FAILURE;
            }

            $photoIds = $rows->pluck('photo_id')->unique()->values()->map('intval')->all();

            $state = [
                'version' => self::STATE_VERSION,
                'entry_id' => $entry['entry_id'],
                'mapping_fingerprint' => $this->mappingFingerprint($entry),
                'baseline_items' => $actual['items'],
                'baseline_xp' => (int) DB::table('photos')->whereIn('id', $photoIds)->sum('xp'),
                'retired_object_id' => (int) $entry['retired_id'],
                'category_id' => (int) $entry['category_id'],
                'tag_ids' => $rows->pluck('id')->map('intval')->all(),
                'all_photo_ids' => $photoIds,
                'pending_photo_ids' => $photoIds,
            ];

            $this->saveState($entry, $state);
            $this->line('  snapshot: ' . count($state['tag_ids']) . ' tag ids, ' . count($photoIds) . ' photos');
        } else {
            if ($state['mapping_fingerprint'] !== $this->mappingFingerprint($entry)) {
                $this->error('  The entry changed since this snapshot was taken. Refusing to resume.');

                return self::FAILURE;
            }

            $this->warn('  resuming from snapshot: ' . count($state['tag_ids']) . ' tag ids, '
                . count($state['pending_photo_ids']) . ' summaries outstanding');
        }

        return $this->runRetirement($entry, $state, $summaries, $metrics) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Sets retired_at + merged_into_id. The picker filters on retired_at (LitterObject::active),
     * and AddTagsToPhotoAction refuses to tag a retired object, so from this point live traffic
     * cannot add rows the snapshot would miss.
     */
    private function closePicker(array $entry): void
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

        $this->repointQuickTags($entry, $desiredCloId);

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

        if ($ok) {
            $this->dropRetiredPivot($entry);
            $this->clearState($entry);
            $this->newLine();
            $this->info('Applied. Next status: '
                . (self::STATUSES[array_search($entry['status'], self::STATUSES, true) + 1] ?? 'none'));
        }

        return $ok;
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
     */
    private function repointQuickTags(array $entry, int $desiredCloId): void
    {
        $retiredCloId = $this->retiredCloId($entry);

        if ($retiredCloId === null) {
            $this->line('  quick tags: retired object has no pivot, nothing to move');

            return;
        }

        // A user may already hold a preset on the surviving CLO. The table is unique per
        // (user, clo, type), so those rows are dropped rather than repointed into a collision.
        $collisions = DB::table('user_quick_tags as a')
            ->join('user_quick_tags as b', function ($join) use ($desiredCloId) {
                $join->on('a.user_id', '=', 'b.user_id')
                    ->where('b.clo_id', '=', $desiredCloId)
                    ->whereRaw('(a.type_id <=> b.type_id)');
            })
            ->where('a.clo_id', $retiredCloId)
            ->pluck('a.id');

        if ($collisions->isNotEmpty()) {
            DB::table('user_quick_tags')->whereIn('id', $collisions)->delete();
            $this->warn('  quick tags: dropped ' . $collisions->count() . ' that would collide with an existing preset');
        }

        $moved = DB::table('user_quick_tags')
            ->where('clo_id', $retiredCloId)
            ->update(['clo_id' => $desiredCloId, 'updated_at' => now()]);

        $this->line("  quick tags: moved {$moved} from CLO {$retiredCloId} to {$desiredCloId}");
    }

    private function dropRetiredPivot(array $entry): void
    {
        $retiredCloId = $this->retiredCloId($entry);

        if ($retiredCloId === null) {
            return;
        }

        $stillReferenced = DB::table('photo_tags')->where('category_litter_object_id', $retiredCloId)->count()
            + DB::table('user_quick_tags')->where('clo_id', $retiredCloId)->count();

        if ($stillReferenced > 0) {
            $this->warn("  retired pivot CLO {$retiredCloId} still has {$stillReferenced} references — left in place");

            return;
        }

        DB::table('category_litter_object')->where('id', $retiredCloId)->delete();
        $this->line("  removed dangling pivot CLO {$retiredCloId}");
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

        // Assert the presets SURVIVED, not that the retired CLO is empty. user_quick_tags.clo_id
        // cascades on delete, and the retired pivot is dropped at the end of a run — so "zero
        // rows on the retired CLO" is true whether the presets were repointed or destroyed.
        $checks['quick tags survived the repoint'] = DB::table('user_quick_tags')
            ->where('clo_id', $desiredCloId)
            ->count() >= (int) $entry['quick_tags_on_retired_clo'];

        $checks['no summary references retired object'] = $this->summariesReferencing($retiredId) === 0;

        $checks['redis reconciles'] = $this->redisReconciles($retiredId) && $this->redisReconciles($desiredId);

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
        $expected = [[RedisKeys::objects(RedisKeys::global()), (string) $objectId, $this->itemsFor($objectId)]];

        $levels = [
            'country_id' => fn (int $id): string => RedisKeys::country($id),
            'state_id' => fn (int $id): string => RedisKeys::state($id),
            'city_id' => fn (int $id): string => RedisKeys::city($id),
        ];

        foreach ($levels as $column => $keyFor) {
            foreach ($this->itemsGroupedBy($objectId, "p.{$column}") as $scopeId => $items) {
                $expected[] = [RedisKeys::objects($keyFor((int) $scopeId)), (string) $objectId, $items];
            }
        }

        foreach ($this->itemsGroupedBy($objectId, 'p.user_id') as $userId => $items) {
            $expected[] = [RedisKeys::user((int) $userId) . ':tags', 'obj:' . $objectId, $items];
        }

        $mismatches = [];

        foreach ($expected as [$key, $field, $mysql]) {
            try {
                $redis = (int) (Redis::hget($key, $field) ?? 0);
            } catch (Throwable $e) {
                $this->error('  Redis unreadable: ' . $e->getMessage());

                return false;
            }

            if ($mysql !== $redis) {
                $mismatches[] = "      {$key} {$field}: MySQL {$mysql} vs Redis {$redis}";
            }
        }

        if ($mismatches !== []) {
            $this->error(sprintf('    object %d: %d of %d scopes disagree', $objectId, count($mismatches), count($expected)));

            foreach (array_slice($mismatches, 0, 10) as $line) {
                $this->line($line);
            }

            $this->line('    Rebuild with: php artisan olm:redis:rebuild (flushes by default)');

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

    /** @return array<int, int> scope id => items */
    private function itemsGroupedBy(int $objectId, string $column): array
    {
        return $this->itemsQuery($objectId)
            ->whereNotNull($column)
            ->selectRaw("{$column} AS scope_id, SUM(pt.quantity) AS items")
            ->groupBy($column)
            ->pluck('items', 'scope_id')
            ->map('intval')
            ->all();
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
        return [
            'rows' => (int) $this->retiredRowQuery($e)->count(),
            'items' => (int) $this->retiredRowQuery($e)->sum('quantity'),
            'photos' => (int) $this->retiredRowQuery($e)->distinct()->count('photo_id'),
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
            'baseline_items', 'baseline_xp', 'retired_object_id', 'category_id'] as $f) {
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
