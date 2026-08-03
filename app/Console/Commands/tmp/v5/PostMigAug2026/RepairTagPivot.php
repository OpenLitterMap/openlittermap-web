<?php

namespace App\Console\Commands\tmp\v5\PostMigAug2026;

use App\Models\Photo;
use App\Services\Tags\GeneratePhotoSummaryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Attaches ONE (category, object) pair's orphaned photo_tags to their category_litter_object
 * pivot. Referential repair only — "operation A" in readme/PostMigration-2026-08.md.
 *
 * Never changed: litter_object_id, category_id, litter_object_type_id, quantity.
 * Rows are never merged or deleted. Taxonomy consolidation is a separate per-tag decision
 * tracked in readme/audit/LitterObjectDecisions-2026-08.csv.
 *
 * Resumability: targets are selected by `photo_tags.category_litter_object_id IS NULL`, NOT
 * by pivot absence, so a run interrupted after pivot creation is still discoverable. Affected
 * photo ids are persisted BEFORE the first write so summary regeneration survives a crash.
 */
class RepairTagPivot extends Command
{
    protected $signature = 'olm:repair-tag-pivot
        {--object= : litter_objects.key to repair}
        {--category= : category key — required with --apply when the object spans several}
        {--all : preview every outstanding pair (cannot be combined with --apply)}
        {--apply : execute the repair (dry-run by default)}
        {--batch=2000 : rows per transaction}
        {--expect-rows= : abort unless the pair has exactly this many orphaned rows}
        {--expect-items= : abort unless the pair has exactly this many items}';

    protected $description = 'Attach one pair of orphaned photo_tags to its category_litter_object pivot. No taxonomy changes.';

    private const STATE_DIR = 'repair-tag-pivot';

    private const STATE_VERSION = 1;

    private bool $apply = false;
    private int $batch = 2000;

    public function handle(GeneratePhotoSummaryService $summaryService): int
    {
        $this->apply = (bool) $this->option('apply');
        $this->batch = max(1, (int) $this->option('batch'));

        $objectKey = $this->option('object');
        $all = (bool) $this->option('all');

        // ── Guard rails: this tool is deliberately one pair at a time ──
        if ($all && $this->apply) {
            $this->error('--all is preview-only. Repair one pair at a time with --object=<key> --apply.');

            return self::FAILURE;
        }

        if ($all && $objectKey) {
            $this->error('--all and --object are mutually exclusive.');

            return self::FAILURE;
        }

        if (!$all && !$objectKey) {
            $this->error('Provide --object=<key>, or --all to preview.');

            return self::FAILURE;
        }

        if ($objectKey && !DB::table('litter_objects')->where('key', $objectKey)->exists()) {
            $this->error("Unknown litter object key: {$objectKey}");

            return self::FAILURE;
        }

        // A mistyped category must fail loudly, not silently succeed as a no-op.
        $categoryKey = $this->option('category');

        if ($categoryKey && !DB::table('categories')->where('key', $categoryKey)->exists()) {
            $this->error("Unknown category key: {$categoryKey}");

            return self::FAILURE;
        }

        $this->line($this->apply ? '<fg=red>LIVE MODE</>' : '<fg=green>DRY RUN</> (pass --apply to execute)');
        $this->newLine();

        $targets = $this->resolveTargets($objectKey, $categoryKey);

        if (empty($targets)) {
            $this->info('Nothing outstanding — no orphaned rows match.');

            return self::SUCCESS;
        }

        // A single object may be tagged across several categories. Applying must name one.
        if ($this->apply && count($targets) > 1) {
            $this->error("'{$objectKey}' spans " . count($targets) . ' categories. Re-run with --category=<key> for each:');
            foreach ($targets as $t) {
                $this->line("  --category={$t->category_key}   ({$t->tag_rows} rows, {$t->items} items)");
            }

            return self::FAILURE;
        }

        if ($this->apply && !$this->expectationsMet($targets[0])) {
            return self::FAILURE;
        }

        $failed = 0;

        foreach ($targets as $t) {
            if (!$this->repairPair($t, $summaryService)) {
                $failed++;
            }
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Outstanding pairs, selected by orphaned ROWS — never by pivot absence, so a run
     * interrupted after the pivot was created remains discoverable.
     *
     * @return array<int, object>
     */
    private function resolveTargets(?string $objectKey, ?string $categoryKey): array
    {
        $q = DB::table('photo_tags as pt')
            ->join('litter_objects as lo', 'lo.id', '=', 'pt.litter_object_id')
            ->join('categories as c', 'c.id', '=', 'pt.category_id')
            ->whereNull('pt.category_litter_object_id')
            ->whereNotNull('pt.litter_object_id')
            ->whereNotNull('pt.category_id');

        if ($objectKey) {
            $q->where('lo.key', $objectKey);
        }

        if ($categoryKey) {
            $q->where('c.key', $categoryKey);
        }

        $fromRows = $q->groupBy('pt.category_id', 'pt.litter_object_id', 'c.key', 'lo.key')
            ->select([
                'pt.category_id',
                'pt.litter_object_id',
                'c.key as category_key',
                'lo.key as object_key',
                DB::raw('COUNT(*) as tag_rows'),
                DB::raw('SUM(pt.quantity) as items'),
                DB::raw('COUNT(DISTINCT pt.photo_id) as photos'),
            ])
            ->orderBy('c.key')
            ->orderBy('lo.key')
            ->get()
            ->keyBy(fn ($r) => "{$r->category_id}-{$r->litter_object_id}")
            ->all();

        // A pair whose rows are all repaired but whose summaries never finished has NO
        // null-CLO rows left, so it is invisible to the query above. Its persisted state is
        // therefore an independent target — otherwise "rerun to retry" is a false promise.
        foreach ($this->pendingStates() as $state) {
            $id = "{$state['category_id']}-{$state['litter_object_id']}";

            if (isset($fromRows[$id])) {
                continue;
            }

            if ($objectKey && $state['object_key'] !== $objectKey) {
                continue;
            }

            if ($categoryKey && $state['category_key'] !== $categoryKey) {
                continue;
            }

            $fromRows[$id] = (object) [
                'category_id' => $state['category_id'],
                'litter_object_id' => $state['litter_object_id'],
                'category_key' => $state['category_key'],
                'object_key' => $state['object_key'],
                'tag_rows' => 0,
                'items' => $state['baseline_items'],
                'photos' => count($state['photo_ids']),
                'summaries_only' => true,
            ];
        }

        return array_values($fromRows);
    }

    /**
     * Every persisted, still-outstanding repair state.
     *
     * @return array<int, array<string, mixed>>
     */
    private function pendingStates(): array
    {
        $out = [];

        foreach (Storage::disk('local')->files(self::STATE_DIR) as $file) {
            if (!str_ends_with($file, '.json')) {
                continue;
            }

            $state = $this->readStateFile($file);

            if ($state !== null && !empty($state['photo_ids'])) {
                $out[] = $state;
            }
        }

        return $out;
    }

    private function expectationsMet(object $t): bool
    {
        foreach ([['expect-rows', 'tag_rows'], ['expect-items', 'items']] as [$opt, $field]) {
            $expected = $this->option($opt);

            if ($expected !== null && (int) $expected !== (int) $t->$field) {
                $this->error("--{$opt}={$expected} but found {$t->$field}. Data changed since the dry run — aborting.");

                return false;
            }
        }

        return true;
    }

    private function statePath(object $t): string
    {
        return self::STATE_DIR . "/{$t->category_id}-{$t->litter_object_id}.json";
    }

    private function repairPair(object $t, GeneratePhotoSummaryService $summaryService): bool
    {
        $summariesOnly = ($t->summaries_only ?? false) === true;

        $this->line(sprintf(
            '<options=bold>%s / %s</>  %s%s items · %s photos',
            $t->category_key,
            $t->object_key,
            $summariesOnly ? '<fg=yellow>rows already repaired</> · ' : number_format($t->tag_rows) . ' orphaned rows · ',
            number_format($t->items),
            number_format($t->photos)
        ));

        if ($summariesOnly) {
            $this->warn('    resuming: pending summary regeneration from a previous run');
        }

        if (!$this->apply) {
            $this->line($summariesOnly
                ? "    would regenerate {$t->photos} outstanding summaries"
                : "    would repair {$t->tag_rows} rows and regenerate {$t->photos} summaries");

            if (!$summariesOnly) {
                $this->line("    verify with: --expect-rows={$t->tag_rows} --expect-items={$t->items}");
            }

            $this->newLine();

            return true;
        }

        $baseline = $this->aggregates($t);

        // Persist affected photo ids BEFORE any write so a crash is recoverable.
        $photoIds = DB::table('photo_tags')
            ->where('category_id', $t->category_id)
            ->where('litter_object_id', $t->litter_object_id)
            ->whereNull('category_litter_object_id')
            ->distinct()
            ->pluck('photo_id')
            ->all();

        $photoIds = array_values(array_unique(array_merge($photoIds, $this->loadState($t))));
        $this->saveState($t, $photoIds);
        $this->line('    state: ' . count($photoIds) . ' photo ids recorded at storage/app/' . $this->statePath($t));

        // Race-safe pivot resolution.
        $cloId = DB::table('category_litter_object')
            ->where('category_id', $t->category_id)
            ->where('litter_object_id', $t->litter_object_id)
            ->value('id');

        if (!$cloId) {
            DB::table('category_litter_object')->insertOrIgnore([
                'category_id' => $t->category_id,
                'litter_object_id' => $t->litter_object_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $cloId = DB::table('category_litter_object')
                ->where('category_id', $t->category_id)
                ->where('litter_object_id', $t->litter_object_id)
                ->value('id');

            $this->line("    pivot created: id={$cloId} (not selectable — TagsConfig governs the picker)");
        }

        $repaired = 0;

        while (true) {
            $ids = DB::table('photo_tags')
                ->where('category_id', $t->category_id)
                ->where('litter_object_id', $t->litter_object_id)
                ->whereNull('category_litter_object_id')
                ->limit($this->batch)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            DB::transaction(function () use ($ids, $cloId, &$repaired) {
                $repaired += DB::table('photo_tags')
                    ->whereIn('id', $ids)
                    ->update(['category_litter_object_id' => $cloId]);
            });
        }

        $this->line("    repaired {$repaired} rows");

        // Regenerate summaries; anything that throws stays in the state file for retry.
        $retry = [];

        foreach (array_chunk($photoIds, 500) as $chunk) {
            foreach (Photo::withTrashed()->whereIn('id', $chunk)->get() as $photo) {
                try {
                    $summaryService->run($photo);
                } catch (Throwable $e) {
                    $retry[] = $photo->id;
                    $this->warn("    summary failed for photo {$photo->id}: {$e->getMessage()}");
                }
            }
        }

        $ok = true;

        if ($retry) {
            $this->saveState($t, $retry);
            $this->error('    ' . count($retry) . ' summaries failed — retained in the state file. Re-run to retry.');
            $ok = false;
        } else {
            $this->clearState($t);
            $this->line('    summaries regenerated: ' . count($photoIds));
        }

        $after = $this->aggregates($t);

        // Quantity is the hard invariant — a CLO-only repair can never change it.
        if ($baseline['items'] !== $after['items']) {
            $this->error("    ITEMS CHANGED: {$baseline['items']} → {$after['items']} — a CLO-only repair must not alter quantity");
            $ok = false;
        } else {
            $this->line("    items unchanged ✓ ({$after['items']})");
        }

        // Summary regeneration recomputes xp/total_tags, so a delta here is legitimate when
        // the denormalised value was already stale. Report it loudly rather than failing —
        // an unexpected delta is a signal to investigate, not proof of corruption.
        foreach (['xp' => 'xp', 'total_tags' => 'total_tags'] as $key => $label) {
            if ($baseline[$key] !== $after[$key]) {
                $delta = $after[$key] - $baseline[$key];
                $this->warn(sprintf(
                    '    %s recomputed by summary regeneration: %d → %d (%+d) — expected only if it was stale',
                    $label,
                    $baseline[$key],
                    $after[$key],
                    $delta
                ));
            } else {
                $this->line("    {$label} unchanged ✓ ({$after[$key]})");
            }
        }

        $remaining = DB::table('photo_tags')
            ->where('category_id', $t->category_id)
            ->where('litter_object_id', $t->litter_object_id)
            ->whereNull('category_litter_object_id')
            ->count();

        if ($remaining > 0) {
            $this->error("    {$remaining} orphaned rows remain");
            $ok = false;
        } else {
            $this->line('    <fg=green>no orphaned rows remain ✓</>');
        }

        $this->newLine();

        return $ok;
    }

    /**
     * Aggregates that a CLO-only repair must leave untouched.
     *
     * @return array{items:int, xp:int, total_tags:int}
     */
    private function aggregates(object $t): array
    {
        $photoIds = DB::table('photo_tags')
            ->where('category_id', $t->category_id)
            ->where('litter_object_id', $t->litter_object_id)
            ->distinct()
            ->pluck('photo_id');

        return [
            'items' => (int) DB::table('photo_tags')
                ->where('category_id', $t->category_id)
                ->where('litter_object_id', $t->litter_object_id)
                ->sum('quantity'),
            'xp' => (int) DB::table('photos')->whereIn('id', $photoIds)->sum('xp'),
            'total_tags' => (int) DB::table('photos')->whereIn('id', $photoIds)->sum('total_tags'),
        ];
    }

    /**
     * Read and VALIDATE a state file. Recovery state drives writes, so anything malformed
     * must abort rather than be silently treated as "nothing to do" — a discarded state file
     * means photos keep stale summaries with no record that they were ever pending.
     *
     * @return array<string, mixed>|null null only when the file does not exist
     *
     * @throws \RuntimeException when the file exists but is not trustworthy
     */
    private function readStateFile(string $path): ?array
    {
        $disk = Storage::disk('local');

        if (!$disk->exists($path)) {
            return null;
        }

        try {
            $state = json_decode($disk->get($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException("Corrupt repair state at {$path}: {$e->getMessage()}. Refusing to continue.");
        }

        foreach (['version', 'category_id', 'category_key', 'litter_object_id', 'object_key', 'photo_ids'] as $field) {
            if (!array_key_exists($field, $state)) {
                throw new \RuntimeException("Repair state at {$path} is missing '{$field}'. Refusing to continue.");
            }
        }

        if ((int) $state['version'] !== self::STATE_VERSION) {
            throw new \RuntimeException("Repair state at {$path} has version {$state['version']}, expected " . self::STATE_VERSION . '.');
        }

        if (!is_array($state['photo_ids']) || array_filter($state['photo_ids'], fn ($id) => !is_int($id))) {
            throw new \RuntimeException("Repair state at {$path} contains non-integer photo ids. Refusing to continue.");
        }

        // The ids must still resolve to the keys recorded when the state was written.
        $actualCategory = DB::table('categories')->where('id', $state['category_id'])->value('key');
        $actualObject = DB::table('litter_objects')->where('id', $state['litter_object_id'])->value('key');

        if ($actualCategory !== $state['category_key'] || $actualObject !== $state['object_key']) {
            throw new \RuntimeException(
                "Repair state at {$path} is stale: recorded {$state['category_key']}/{$state['object_key']}, "
                . "database now has {$actualCategory}/{$actualObject}. Refusing to continue."
            );
        }

        $state['baseline_items'] = (int) ($state['baseline_items'] ?? 0);

        return $state;
    }

    /** @return array<int, int> */
    private function loadState(object $t): array
    {
        return $this->readStateFile($this->statePath($t))['photo_ids'] ?? [];
    }

    /** @param array<int, int> $photoIds */
    private function saveState(object $t, array $photoIds): void
    {
        Storage::disk('local')->put($this->statePath($t), json_encode([
            'version' => self::STATE_VERSION,
            'category_id' => (int) $t->category_id,
            'category_key' => $t->category_key,
            'litter_object_id' => (int) $t->litter_object_id,
            'object_key' => $t->object_key,
            'baseline_rows' => (int) $t->tag_rows,
            'baseline_items' => (int) $t->items,
            'photo_ids' => array_values(array_map('intval', $photoIds)),
        ], JSON_THROW_ON_ERROR));
    }

    private function clearState(object $t): void
    {
        Storage::disk('local')->delete($this->statePath($t));
    }
}
