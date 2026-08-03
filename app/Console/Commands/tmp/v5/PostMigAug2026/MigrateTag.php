<?php

namespace App\Console\Commands\tmp\v5\PostMigAug2026;

use App\Models\Photo;
use App\Services\Metrics\MetricsService;
use App\Services\Tags\GeneratePhotoSummaryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Migrates ONE approved (category, object) pair from an old tag to a new one.
 *
 * Driven entirely by an approved row in readme/audit/TagMigrationQueue-2026-08.csv — the
 * command never infers a mapping. One entry, one transformation, one run. There is
 * deliberately no bulk apply.
 *
 * Automated (deterministic):
 *   - repoints an IMMUTABLE, pre-snapshotted set of photo_tags rows to the target
 *     object + CLO, preserving category, type, quantity, extras and physical rows
 *   - regenerates photos.summary for exactly those photos
 *   - re-runs MetricsService for processed photos, moving per-object counts from the old
 *     object id to the new one (totals and XP must not move)
 *   - repoints user_quick_tags.clo_id
 *
 * Manual per tag (gated by status, never automated):
 *   TagsConfig, BrandsConfig, alias normalisation, translations, API compatibility,
 *   export regression test, docs/changelog.
 *
 * The source litter_objects row is left in place as an unselectable tombstone. Deleting
 * retired objects is a later garbage-collection pass, once references are proven zero.
 */
class MigrateTag extends Command
{
    protected $signature = 'olm:migrate-tag
        {--list : show the migration queue}
        {--entry= : queue entry_id to operate on}
        {--apply : execute (dry-run by default)}
        {--advance= : record a manual status transition}
        {--by= : who performed the transition (required with --advance)}
        {--evidence= : verification evidence (required for *_VERIFIED transitions)}
        {--batch=2000 : rows per transaction}
        {--queue= : override the queue file (relative to base_path); for rehearsals and tests}';

    protected $description = 'Migrate one approved old→new tag pair. Queue-driven, one entry at a time.';

    private const QUEUE = 'readme/audit/TagMigrationQueue-2026-08.csv';
    private const STATE_DIR = 'migrate-tag';
    private const STATE_VERSION = 1;

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

    /** Data may only be written once the manual code changes are recorded as done. */
    private const APPLY_REQUIRES = 'CODE_UPDATED';

    public function handle(GeneratePhotoSummaryService $summaries, MetricsService $metrics): int
    {
        if ($this->option('list') || !$this->option('entry')) {
            return $this->listQueue();
        }

        $rows = $this->loadQueue();
        $entryId = $this->option('entry');
        $entry = collect($rows)->firstWhere('entry_id', $entryId);

        if (!$entry) {
            $this->error("Unknown queue entry: {$entryId}");

            return self::FAILURE;
        }

        if ($this->option('advance')) {
            return $this->advance($rows, $entry);
        }

        return $this->option('apply')
            ? $this->apply($entry, $summaries, $metrics)
            : $this->dryRun($entry);
    }

    // ── Queue ────────────────────────────────────────────────────────────────

    private function listQueue(): int
    {
        $rows = $this->loadQueue();

        $this->table(
            ['entry', 'status', 'transformation', 'rows', 'items'],
            array_map(fn ($r) => [
                $r['entry_id'],
                $r['status'],
                "{$r['source_category_key']}/{$r['source_object_key']} → {$r['target_category_key']}/{$r['target_object_key']}",
                number_format((int) $r['expected_rows']),
                number_format((int) $r['expected_items']),
            ], $rows)
        );

        $done = count(array_filter($rows, fn ($r) => $r['status'] === 'COMPLETE'));
        $this->line('  ' . $done . '/' . count($rows) . ' complete');
        $this->newLine();
        $this->line('Next step for an entry: php artisan olm:migrate-tag --entry=<id>');

        return self::SUCCESS;
    }

    private function advance(array $rows, array $entry): int
    {
        $to = strtoupper($this->option('advance'));
        $by = $this->option('by');

        if (!in_array($to, self::STATUSES, true)) {
            $this->error("Unknown status: {$to}. One of: " . implode(', ', self::STATUSES));

            return self::FAILURE;
        }

        if (!$by) {
            $this->error('--advance requires --by="..." so every transition is attributable.');

            return self::FAILURE;
        }

        $currentIdx = array_search($entry['status'], self::STATUSES, true);
        $targetIdx = array_search($to, self::STATUSES, true);

        if ($targetIdx !== $currentIdx + 1) {
            $this->error("Cannot jump {$entry['status']} → {$to}. Next is: " . (self::STATUSES[$currentIdx + 1] ?? 'none'));

            return self::FAILURE;
        }

        if (str_ends_with($to, '_VERIFIED') && !$this->option('evidence')) {
            $this->error("--advance={$to} requires --evidence=\"...\" recording what was checked.");

            return self::FAILURE;
        }

        foreach ($rows as &$row) {
            if ($row['entry_id'] === $entry['entry_id']) {
                $row['status'] = $to;
                $row['approver'] = $by;
                $row['approved_at'] = now()->toDateString();

                if ($this->option('evidence')) {
                    $row['verification_evidence'] = trim($row['verification_evidence'] . ' | ' . $this->option('evidence'), ' |');
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

        $actual = $this->measure($entry);

        $this->line('  Measured now:');
        $this->line("    rows   {$actual['rows']}   (expected {$entry['expected_rows']})");
        $this->line("    items  {$actual['items']}   (expected {$entry['expected_items']})");
        $this->line("    photos {$actual['photos']}  (expected {$entry['expected_photos']})");
        $this->newLine();

        if (!$this->expectationsMatch($entry, $actual)) {
            return self::FAILURE;
        }

        $this->info('Expectations match.');
        $this->newLine();
        $this->line('Manual work required before --apply:');
        foreach (explode(';', $entry['affected_code']) as $item) {
            if (trim($item) !== '') {
                $this->line('    ☐ ' . trim($item));
            }
        }
        $this->newLine();
        $this->line("When done: php artisan olm:migrate-tag --entry={$entry['entry_id']} --advance=CODE_UPDATED --by=\"you\"");

        return self::SUCCESS;
    }

    // ── Apply ────────────────────────────────────────────────────────────────

    private function apply(array $entry, GeneratePhotoSummaryService $summaries, MetricsService $metrics): int
    {
        $statusIdx = array_search($entry['status'], self::STATUSES, true);
        $requiredIdx = array_search(self::APPLY_REQUIRES, self::STATUSES, true);

        if ($statusIdx < $requiredIdx) {
            $this->error("Entry is {$entry['status']}; --apply requires at least " . self::APPLY_REQUIRES . '.');
            $this->line('  The manual code/config changes must be recorded before data is written.');

            return self::FAILURE;
        }

        $lock = self::STATE_DIR . "/{$entry['entry_id']}.lock";

        if (Storage::disk('local')->exists($lock)) {
            $this->error('Another run holds the lock for this entry: storage/app/' . $lock);
            $this->line('  Delete it only if you are certain no run is in progress.');

            return self::FAILURE;
        }

        $this->describe($entry);

        $state = $this->loadState($entry);

        // Snapshot the EXACT rows once. Later runs reuse the snapshot, so a row inserted
        // after approval is never silently swept into this migration.
        if ($state === null) {
            $actual = $this->measure($entry);

            if (!$this->expectationsMatch($entry, $actual)) {
                return self::FAILURE;
            }

            $tagIds = $this->sourceRowQuery($entry)->pluck('id')->map('intval')->all();
            $photoIds = $this->sourceRowQuery($entry)->distinct()->pluck('photo_id')->map('intval')->all();

            $state = [
                'version' => self::STATE_VERSION,
                'entry_id' => $entry['entry_id'],
                'baseline_rows' => (int) $entry['expected_rows'],
                'baseline_items' => (int) $entry['expected_items'],
                'tag_ids' => $tagIds,
                'photo_ids' => $photoIds,
                'rows_done' => false,
            ];

            $this->saveState($entry, $state);
            $this->line('  snapshot: ' . count($tagIds) . ' tag ids, ' . count($photoIds) . ' photos');
        } else {
            $this->warn('  resuming from snapshot: ' . count($state['tag_ids']) . ' tag ids');
        }

        Storage::disk('local')->put($lock, (string) now());

        try {
            $ok = $this->runMigration($entry, $state, $summaries, $metrics);
        } finally {
            Storage::disk('local')->delete($lock);
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    private function runMigration(array $entry, array $state, GeneratePhotoSummaryService $summaries, MetricsService $metrics): bool
    {
        $targetObjectId = (int) $entry['target_object_id'];
        $targetCloId = (int) $entry['target_clo_id'];
        $targetCategoryId = (int) $entry['target_category_id'];

        // The target pivot must already exist and match. Never invent one here.
        $pivot = DB::table('category_litter_object')
            ->where('id', $targetCloId)
            ->where('category_id', $targetCategoryId)
            ->where('litter_object_id', $targetObjectId)
            ->exists();

        if (!$pivot) {
            $this->error("Target CLO {$targetCloId} is not ({$targetCategoryId}, {$targetObjectId}). Refusing to continue.");

            return false;
        }

        $itemsBefore = $this->itemsFor($state['tag_ids']);
        $xpBefore = (int) DB::table('photos')->whereIn('id', $state['photo_ids'])->sum('xp');

        // 1. Repoint the immutable snapshot only.
        if (!$state['rows_done']) {
            $moved = 0;

            foreach (array_chunk($state['tag_ids'], (int) $this->option('batch')) as $chunk) {
                DB::transaction(function () use ($chunk, $targetObjectId, $targetCloId, &$moved) {
                    $moved += DB::table('photo_tags')
                        ->whereIn('id', $chunk)
                        ->update([
                            'litter_object_id' => $targetObjectId,
                            'category_litter_object_id' => $targetCloId,
                        ]);
                });
            }

            $this->line("  repointed {$moved} rows → object {$targetObjectId}, CLO {$targetCloId}");

            $state['rows_done'] = true;
            $this->saveState($entry, $state);
        } else {
            $this->line('  rows already repointed — resuming at summaries');
        }

        // 2. Summaries, then 3. metrics delta (moves per-object counts, leaves totals/XP).
        $failed = [];
        $processed = 0;

        foreach (array_chunk($state['photo_ids'], 200) as $chunk) {
            foreach (Photo::withTrashed()->whereIn('id', $chunk)->get() as $photo) {
                try {
                    $summaries->run($photo);

                    if ($photo->processed_at !== null) {
                        $metrics->processPhoto($photo->fresh());
                    }

                    $processed++;
                } catch (Throwable $e) {
                    $failed[] = $photo->id;
                    $this->warn("    photo {$photo->id}: {$e->getMessage()}");
                }
            }
        }

        $this->line("  summaries + metrics: {$processed} photos");

        // 4. Stored CLO consumers.
        $quickTags = DB::table('user_quick_tags')
            ->whereIn('clo_id', DB::table('category_litter_object')
                ->where('litter_object_id', (int) $entry['source_object_id'])
                ->pluck('id'))
            ->update(['clo_id' => $targetCloId]);

        if ($quickTags > 0) {
            $this->line("  user_quick_tags repointed: {$quickTags}");
        }

        // ── Verification ──
        $ok = true;

        $itemsAfter = $this->itemsFor($state['tag_ids']);
        $xpAfter = (int) DB::table('photos')->whereIn('id', $state['photo_ids'])->sum('xp');

        if ($itemsBefore !== $itemsAfter) {
            $this->error("  ITEMS CHANGED {$itemsBefore} → {$itemsAfter}");
            $ok = false;
        } else {
            $this->line("  items unchanged ✓ ({$itemsAfter})");
        }

        if ($xpBefore !== $xpAfter) {
            $this->error("  XP CHANGED {$xpBefore} → {$xpAfter} — a rename must not move XP");
            $ok = false;
        } else {
            $this->line("  xp unchanged ✓ ({$xpAfter})");
        }

        $remaining = $this->sourceRowQuery($entry)->count();

        if ($remaining > 0) {
            $this->error("  {$remaining} source rows remain");
            $ok = false;
        } else {
            $this->line('  source pair drained ✓');
        }

        if ($failed) {
            $state['photo_ids'] = $failed;
            $this->saveState($entry, $state);
            $this->error('  ' . count($failed) . ' photos failed — retained in state, re-run to retry');

            return false;
        }

        if ($ok) {
            $this->clearState($entry);
            $this->newLine();
            $this->info('Applied. Next: verify exports/APIs, then --advance=LOCAL_VERIFIED --by="you" --evidence="..."');
        }

        return $ok;
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function describe(array $e): void
    {
        $this->newLine();
        $this->line("<options=bold>{$e['entry_id']}</>   status: {$e['status']}");
        $this->line("  {$e['source_category_key']}/{$e['source_object_key']} (object {$e['source_object_id']})");
        $this->line("    → {$e['target_category_key']}/{$e['target_object_key']} (object {$e['target_object_id']}, CLO {$e['target_clo_id']})");
        $this->newLine();
    }

    private function sourceRowQuery(array $e)
    {
        return DB::table('photo_tags')
            ->where('category_id', (int) $e['source_category_id'])
            ->where('litter_object_id', (int) $e['source_object_id']);
    }

    /** @return array{rows:int, items:int, photos:int} */
    private function measure(array $e): array
    {
        return [
            'rows' => (int) $this->sourceRowQuery($e)->count(),
            'items' => (int) $this->sourceRowQuery($e)->sum('quantity'),
            'photos' => (int) $this->sourceRowQuery($e)->distinct()->count('photo_id'),
        ];
    }

    /** @param array<int, int> $ids */
    private function itemsFor(array $ids): int
    {
        return (int) DB::table('photo_tags')->whereIn('id', $ids)->sum('quantity');
    }

    private function expectationsMatch(array $entry, array $actual): bool
    {
        foreach (['rows', 'items', 'photos'] as $field) {
            $expected = (int) $entry["expected_{$field}"];

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

        foreach (['version', 'entry_id', 'tag_ids', 'photo_ids', 'baseline_rows', 'baseline_items', 'rows_done'] as $f) {
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
        // Atomic: write beside, then move into place.
        $disk = Storage::disk('local');
        $tmp = $this->statePath($e) . '.tmp';

        $disk->put($tmp, json_encode($state, JSON_THROW_ON_ERROR));
        $disk->delete($this->statePath($e));
        $disk->move($tmp, $this->statePath($e));
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
