<?php

namespace App\Console\Commands\tmp\v5\PostMigAug2026;

use App\Console\Commands\tmp\v5\Migration\FixOrphanedTags;
use App\Tags\TagsConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;

/**
 * Review queue for the post-migration taxonomy cleanup.
 *
 * One row per litter object. Rows carrying an `uncertainty` are the ones needing a human
 * decision; the rest are recorded so "review every tag" is provably complete.
 *
 * The queue lives in a git-tracked CSV so decisions are reviewable in PRs, survive database
 * resets, and cannot be silently lost. `--rebuild` refreshes the measured columns while
 * preserving every decision already made.
 *
 * This command NEVER changes tag data. It only reads the database and writes the queue file.
 */
class TagReview extends Command
{
    protected $signature = 'olm:tag-review
        {--rebuild : refresh measured columns from the database, preserving decisions}
        {--all : list every object, not just the unresolved uncertain ones}
        {--show= : print full detail for one object key}
        {--done= : mark an object key reviewed}
        {--decision= : the decision text (required with --done)}
        {--by= : who decided (required with --done)}
        {--reopen= : clear a previous decision}
        {--status : print progress only}';

    protected $description = 'Review queue for post-migration tag cleanup — list uncertain tags and mark them completed.';

    private const QUEUE = 'readme/audit/TagReviewQueue-2026-08.csv';

    private const COLUMNS = [
        'object_id', 'object_key', 'in_tagsconfig', 'total_items', 'pivotless_items',
        'categories_used', 'proposed_action', 'uncertainty',
        'status', 'decision', 'decided_by', 'decided_at', 'notes',
    ];

    public function handle(): int
    {
        if ($this->option('rebuild')) {
            return $this->rebuild();
        }

        if (!file_exists(base_path(self::QUEUE))) {
            $this->error('Queue not found. Build it with: php artisan olm:tag-review --rebuild');

            return self::FAILURE;
        }

        $rows = $this->load();

        if ($key = $this->option('show')) {
            return $this->show($rows, $key);
        }

        if ($key = $this->option('done')) {
            return $this->markDone($rows, $key);
        }

        if ($key = $this->option('reopen')) {
            return $this->reopen($rows, $key);
        }

        if ($this->option('status')) {
            $this->progress($rows);

            return self::SUCCESS;
        }

        return $this->listQueue($rows);
    }

    // ── Queue construction ───────────────────────────────────────────────────

    private function rebuild(): int
    {
        $existing = file_exists(base_path(self::QUEUE))
            ? collect($this->load())->keyBy('object_key')->all()
            : [];

        $canonical = [];
        foreach (TagsConfig::get() as $catKey => $objects) {
            foreach (array_keys($objects) as $objKey) {
                $canonical[$objKey][] = $catKey;
            }
        }

        $cmd = new FixOrphanedTags();
        $ref = new ReflectionMethod($cmd, 'buildMappings');
        $ref->setAccessible(true);
        $mappings = collect($ref->invoke($cmd))->groupBy('orphan_lo_id');

        $objKeyById = DB::table('litter_objects')->pluck('key', 'id')->all();
        $catKeyById = DB::table('categories')->pluck('key', 'id')->all();

        $brandsConfig = @file_get_contents(base_path('app/Tags/BrandsConfig.php')) ?: '';

        $stats = DB::select("
            SELECT lo.id, lo.`key`, lo.crowdsourced,
                   COALESCE(SUM(pt.quantity), 0) AS items,
                   COALESCE(SUM(CASE WHEN pt.category_litter_object_id IS NULL THEN pt.quantity ELSE 0 END), 0) AS pivotless_items,
                   GROUP_CONCAT(DISTINCT c.`key` ORDER BY c.`key` SEPARATOR '|') AS cats
              FROM litter_objects lo
              LEFT JOIN photo_tags pt ON pt.litter_object_id = lo.id
              LEFT JOIN categories c  ON c.id = pt.category_id
             GROUP BY lo.id, lo.`key`, lo.crowdsourced
             ORDER BY lo.`key`
        ");

        // Duplicate candidates — proposed, never auto-approved.
        $norm = fn (string $k) => preg_replace('/s$/', '', strtolower(str_replace('_', '', $k)));
        $groups = [];
        foreach ($stats as $s) {
            $groups[$norm($s->key)][] = $s->key;
        }

        $out = [];

        foreach ($stats as $s) {
            $isCanonical = isset($canonical[$s->key]);

            $targets = collect($mappings[$s->id] ?? [])
                ->map(fn ($m) => ($catKeyById[$m['target_category_id']] ?? '?') . '/' . ($objKeyById[$m['target_lo_id']] ?? '?'))
                ->unique()->values()->all();

            $uncertainty = [];

            if (!$isCanonical) {
                $uncertainty[] = 'legacy key not in TagsConfig';
            }

            if (collect($targets)->contains(fn ($t) => str_ends_with($t, '/other'))) {
                $uncertainty[] = 'LOSSY: proposed merge into generic "other"';
            }

            foreach ($targets as $t) {
                if (!in_array(explode('/', $t)[0], explode('|', (string) $s->cats), true)) {
                    $uncertainty[] = 'CATEGORY MOVE to ' . explode('/', $t)[0];
                }
            }

            $dupes = array_values(array_diff($groups[$norm($s->key)] ?? [], [$s->key]));
            if ($dupes) {
                $uncertainty[] = 'duplicate candidate: ' . implode(',', $dupes) . ' (CANDIDATE ONLY)';
            }

            if (in_array($s->key, ['crisp_small', 'crisp_large'], true)) {
                $uncertainty[] = 'size unrepresentable — photo_tags has no size column';
            }

            if ($isCanonical && $s->pivotless_items > 0) {
                $uncertainty[] = 'canonical object tagged in a category with no pivot';
            }

            // A BrandsConfig reference only matters when the key is changing: retiring it
            // without updating that config lets AutoCreateBrandRelationships recreate the
            // object and its pivot. For objects we are keeping, it is not a concern.
            if ($uncertainty && substr_count($brandsConfig, "'{$s->key}'") > 0) {
                $uncertainty[] = 'BrandsConfig references this key — must update together';
            }

            $prior = $existing[$s->key] ?? null;

            $out[] = [
                'object_id' => $s->id,
                'object_key' => $s->key,
                'in_tagsconfig' => $isCanonical ? 'yes' : 'no',
                'total_items' => (int) $s->items,
                'pivotless_items' => (int) $s->pivotless_items,
                'categories_used' => (string) $s->cats,
                'proposed_action' => $targets ? 'merge → ' . implode(' , ', $targets) : 'keep',
                'uncertainty' => implode('; ', $uncertainty),
                // A recorded decision is preserved verbatim. Anything else is recomputed, so
                // a row whose uncertainty has been resolved upstream stops being PENDING.
                'status' => ($prior['status'] ?? '') === 'REVIEWED'
                    ? 'REVIEWED'
                    : ($uncertainty ? 'PENDING' : 'NO_REVIEW_NEEDED'),
                'decision' => $prior['decision'] ?? '',
                'decided_by' => $prior['decided_by'] ?? '',
                'decided_at' => $prior['decided_at'] ?? '',
                'notes' => $prior['notes'] ?? '',
            ];
        }

        $this->save($out);

        $pending = count(array_filter($out, fn ($r) => $r['status'] === 'PENDING'));
        $this->info('Queue rebuilt: ' . count($out) . " objects, {$pending} needing review.");
        $this->line('  ' . self::QUEUE);

        return self::SUCCESS;
    }

    // ── Actions ──────────────────────────────────────────────────────────────

    private function listQueue(array $rows): int
    {
        $show = $this->option('all')
            ? $rows
            : array_filter($rows, fn ($r) => $r['status'] === 'PENDING');

        if (empty($show)) {
            $this->info('Nothing pending — every uncertain tag has been reviewed.');
            $this->progress($rows);

            return self::SUCCESS;
        }

        // Biggest impact first — that is where a wrong call costs most.
        usort($show, fn ($a, $b) => (int) $b['total_items'] <=> (int) $a['total_items']);

        $this->table(
            ['key', 'items', 'status', 'proposed', 'uncertainty'],
            array_map(fn ($r) => [
                $r['object_key'],
                number_format((int) $r['total_items']),
                $r['status'],
                mb_strimwidth($r['proposed_action'], 0, 34, '…'),
                mb_strimwidth($r['uncertainty'], 0, 60, '…'),
            ], $show)
        );

        $this->progress($rows);
        $this->newLine();
        $this->line('Detail : php artisan olm:tag-review --show=<key>');
        $this->line('Resolve: php artisan olm:tag-review --done=<key> --decision="..." --by="you"');

        return self::SUCCESS;
    }

    private function show(array $rows, string $key): int
    {
        $row = collect($rows)->firstWhere('object_key', $key);

        if (!$row) {
            $this->error("Unknown object key: {$key}");

            return self::FAILURE;
        }

        $this->newLine();
        $this->line("<options=bold>{$row['object_key']}</>  (id {$row['object_id']})");
        $this->newLine();

        foreach (self::COLUMNS as $col) {
            if (in_array($col, ['object_id', 'object_key'], true) || $row[$col] === '') {
                continue;
            }
            $this->line(sprintf('  %-16s %s', $col, $row[$col]));
        }

        foreach (explode('; ', $row['uncertainty']) as $u) {
            if ($u !== '') {
                $this->warn("  ⚠ {$u}");
            }
        }

        return self::SUCCESS;
    }

    private function markDone(array $rows, string $key): int
    {
        $decision = $this->option('decision');
        $by = $this->option('by');

        if (!$decision || !$by) {
            $this->error('--done requires --decision="..." and --by="..." so the record is auditable.');

            return self::FAILURE;
        }

        $found = false;

        foreach ($rows as &$row) {
            if ($row['object_key'] !== $key) {
                continue;
            }

            $found = true;
            $row['status'] = 'REVIEWED';
            $row['decision'] = $decision;
            $row['decided_by'] = $by;
            $row['decided_at'] = now()->toDateString();
        }
        unset($row);

        if (!$found) {
            $this->error("Unknown object key: {$key}");

            return self::FAILURE;
        }

        $this->save($rows);
        $this->info("✓ {$key} marked REVIEWED — {$decision}");
        $this->progress($rows);

        return self::SUCCESS;
    }

    private function reopen(array $rows, string $key): int
    {
        $found = false;

        foreach ($rows as &$row) {
            if ($row['object_key'] !== $key) {
                continue;
            }

            $found = true;
            $row['status'] = 'PENDING';
            $row['decision'] = '';
            $row['decided_by'] = '';
            $row['decided_at'] = '';
        }
        unset($row);

        if (!$found) {
            $this->error("Unknown object key: {$key}");

            return self::FAILURE;
        }

        $this->save($rows);
        $this->info("{$key} reopened.");

        return self::SUCCESS;
    }

    private function progress(array $rows): void
    {
        $needing = array_filter($rows, fn ($r) => $r['uncertainty'] !== '');
        $done = array_filter($needing, fn ($r) => $r['status'] === 'REVIEWED');

        $total = count($needing);
        $n = count($done);
        $pct = $total ? (int) round($n / $total * 100) : 100;
        $bar = str_repeat('█', (int) ($pct / 5)) . str_repeat('░', 20 - (int) ($pct / 5));

        $this->newLine();
        $this->line("  {$bar}  {$n}/{$total} reviewed ({$pct}%)   " . count($rows) . ' objects total');

        $items = array_sum(array_map(fn ($r) => (int) $r['total_items'], array_filter($needing, fn ($r) => $r['status'] === 'PENDING')));
        if ($items > 0) {
            $this->line('  ' . number_format($items) . ' items still awaiting a decision');
        }
    }

    // ── CSV I/O ──────────────────────────────────────────────────────────────

    /** @return array<int, array<string, string>> */
    private function load(): array
    {
        $fh = fopen(base_path(self::QUEUE), 'r');
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

    /** @param array<int, array<string, mixed>> $rows */
    private function save(array $rows): void
    {
        // Deterministic order so diffs stay readable.
        usort($rows, fn ($a, $b) => strcmp($a['object_key'], $b['object_key']));

        $fh = fopen(base_path(self::QUEUE), 'w');
        fputcsv($fh, self::COLUMNS);

        foreach ($rows as $row) {
            fputcsv($fh, array_map(fn ($c) => $row[$c] ?? '', self::COLUMNS));
        }

        fclose($fh);
    }
}
