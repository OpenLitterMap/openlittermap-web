<?php

declare(strict_types=1);

namespace App\Console\Commands\tmp\v5\PostMigAug2026;

use App\Enums\XpScore;
use App\Exports\CreateCSVExport;
use App\Http\Controllers\API\Tags\GetTagsController;
use App\Services\Redis\RedisKeys;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * READ-ONLY before/after measurement harness for one tag retirement.
 *
 * Writes a deterministic JSON artefact describing every surface a retirement touches, so two
 * captures can be diffed and the invariants asserted. Nothing here mutates data — no writes,
 * no schema changes, no cache warming.
 *
 * Determinism contract: the diffed payload contains no timestamps, no run metadata and no
 * unordered collections. Every map is recursively key-sorted before encoding. Label, date and
 * database name go in a sidecar that is never diffed. Two back-to-back captures of an
 * unchanged database MUST be byte-identical.
 *
 * @see readme/PostTagMigrationClean.md
 */
class TagRetirementSnapshot extends Command
{
    protected $signature = 'olm:tag-retirement-snapshot
        {--entry= : retirement entry id}
        {--label= : capture label; used for the filename only, never in the payload}
        {--diff= : compare two labels, comma separated (e.g. --diff=before,after)}
        {--list : show known retirement entries}';

    protected $description = 'Read-only before/after capture for a tag retirement. Never mutates.';

    private const ARTEFACT_DIR = 'tag-retirement-snapshots';

    /**
     * Incident baseline. Every capture asserts this before writing anything, so a mutated or
     * partially restored database is caught immediately rather than producing a plausible
     * artefact.
     */
    private const BASELINE_PIVOTLESS_ROWS = 189518;
    private const BASELINE_PIVOTLESS_ITEMS = 277169;

    /**
     * Approved retirements, keyed by entry id.
     *
     * Held here rather than in a CSV because the authoritative list
     * (readme/audit/TagRetirements-2026-08.csv) does not exist yet, and this task is scoped
     * read-only — no queue or CSV changes. The direction below reflects decision D-4 and
     * deliberately supersedes the stale row in TagMigrationQueue-2026-08.csv, which encodes
     * the pre-D-4 direction and is left untouched.
     *
     * @var array<string, array<string, int|string>>
     */
    private const RETIREMENTS = [
        'other--plastic_bag' => [
            'category_key' => 'other',
            'category_id' => 12,
            'retired_key' => 'plastic_bag',
            'retired_id' => 92,
            'desired_key' => 'plasticBags',
            'desired_id' => 149,
        ],
    ];

    public function handle(): int
    {
        if ($this->option('list')) {
            return $this->listEntries();
        }

        if ($this->option('diff')) {
            return $this->diff();
        }

        return $this->capture();
    }

    private function listEntries(): int
    {
        $this->table(
            ['entry', 'retire', 'keep'],
            array_map(fn (string $id, array $e) => [
                $id,
                "{$e['category_key']}/{$e['retired_key']} ({$e['retired_id']})",
                "{$e['category_key']}/{$e['desired_key']} ({$e['desired_id']})",
            ], array_keys(self::RETIREMENTS), self::RETIREMENTS)
        );

        return self::SUCCESS;
    }

    // ── Capture ──────────────────────────────────────────────────────────────

    private function capture(): int
    {
        $entry = $this->resolveEntry();

        if ($entry === null) {
            return self::FAILURE;
        }

        $label = (string) $this->option('label');

        if ($label === '' || !preg_match('/^[a-z0-9_-]+$/i', $label)) {
            $this->error('--label is required and must match [a-z0-9_-]+');

            return self::FAILURE;
        }

        // The incident baseline is a PRE-STATE guard: it proves the database is an unmutated
        // restore before a `before` capture is trusted. A retirement deliberately reduces the
        // pivotless count (creating the surviving object's pivot is the point), so asserting it
        // after the fact would fail on the intended change. The numbers are recorded in the
        // payload either way, so the diff still shows the movement.
        if ($label === 'before' && !$this->assertBaseline()) {
            return self::FAILURE;
        }

        $this->line('  capturing…');

        $payload = $this->deepKsort([
            'api' => $this->captureApi($entry),
            'pivotless' => $this->capturePivotless(),
            'entry' => $this->captureEntry($entry),
            'export' => $this->captureExport($entry),
            'mysql' => $this->captureMysql($entry),
            'quick_tags' => $this->captureQuickTags($entry),
            'reconciliation' => $this->captureReconciliation($entry),
            'redis' => $this->captureRedis($entry),
            'structure' => $this->captureStructure($entry),
            'summaries' => $this->captureSummaries($entry),
            'xp_weighting' => $this->captureXpWeighting($entry),
        ]);

        $this->writeArtefact($entry, $label, $payload);

        return self::SUCCESS;
    }

    /**
     * Abort before writing if the database is not the documented incident baseline. A capture
     * from a mutated restore is worse than no capture — it looks authoritative.
     */
    private function assertBaseline(): bool
    {
        $row = DB::selectOne('
            SELECT COUNT(*) AS rows_ct, COALESCE(SUM(pt.quantity), 0) AS items
            FROM photo_tags pt
            WHERE pt.category_id IS NOT NULL AND pt.litter_object_id IS NOT NULL
              AND NOT EXISTS (
                SELECT 1 FROM category_litter_object clo
                 WHERE clo.category_id = pt.category_id
                   AND clo.litter_object_id = pt.litter_object_id)
        ');

        $rows = (int) $row->rows_ct;
        $items = (int) $row->items;

        if ($rows !== self::BASELINE_PIVOTLESS_ROWS || $items !== self::BASELINE_PIVOTLESS_ITEMS) {
            $this->error('  Baseline mismatch — refusing to write an artefact.');
            $this->line(sprintf('    expected %s rows / %s items',
                number_format(self::BASELINE_PIVOTLESS_ROWS), number_format(self::BASELINE_PIVOTLESS_ITEMS)));
            $this->line(sprintf('    found    %s rows / %s items', number_format($rows), number_format($items)));
            $this->line('  Restore olm_postmig_2 from the .sql source of truth and re-run.');

            return false;
        }

        $this->line(sprintf('  baseline ✓ %s pivotless rows / %s items',
            number_format($rows), number_format($items)));

        return true;
    }

    /**
     * Rows referencing a (category, object) pair with no pivot. Recorded rather than only
     * asserted, so the diff shows the retirement's intended effect: creating the surviving
     * object's pivot moves its rows out of this count.
     *
     * @return array<string, int>
     */
    private function capturePivotless(): array
    {
        $row = DB::selectOne('
            SELECT COUNT(*) AS rows_ct, COALESCE(SUM(pt.quantity), 0) AS items
            FROM photo_tags pt
            WHERE pt.category_id IS NOT NULL AND pt.litter_object_id IS NOT NULL
              AND NOT EXISTS (
                SELECT 1 FROM category_litter_object clo
                 WHERE clo.category_id = pt.category_id
                   AND clo.litter_object_id = pt.litter_object_id)
        ');

        return ['items' => (int) $row->items, 'rows' => (int) $row->rows_ct];
    }

    /** @return array<string, mixed> */
    private function captureEntry(array $e): array
    {
        return [
            'category_id' => $e['category_id'],
            'category_key' => $e['category_key'],
            'desired_id' => $e['desired_id'],
            'desired_key' => $e['desired_key'],
            'retired_id' => $e['retired_id'],
            'retired_key' => $e['retired_key'],
        ];
    }

    /**
     * XP is weighted by OBJECT KEY, not by object id — XpCalculator resolves
     * summary['keys']['objects'][$id] through XpScore::getObjectXp(). Repointing rewrites that
     * key, so XP only survives a retirement when both keys carry the same weight.
     *
     * Captured as a computed precondition rather than assumed, because it is false for at
     * least one approved twin (bags_litter = 10 XP vs bagsLitter = 1 XP).
     *
     * @return array<string, mixed>
     */
    private function captureXpWeighting(array $e): array
    {
        $retiredXp = XpScore::getObjectXp((string) $e['retired_key']);
        $desiredXp = XpScore::getObjectXp((string) $e['desired_key']);

        return [
            'desired_xp_per_item' => $desiredXp,
            'equivalent' => $retiredXp === $desiredXp,
            'retired_xp_per_item' => $retiredXp,
            'xp_delta_per_item' => $desiredXp - $retiredXp,
        ];
    }

    /** @return array<string, mixed> */
    private function captureMysql(array $e): array
    {
        $perObject = [];

        foreach (['desired' => $e['desired_id'], 'retired' => $e['retired_id']] as $role => $objectId) {
            $row = DB::selectOne('
                SELECT COUNT(*) AS rows_ct,
                       COALESCE(SUM(pt.quantity), 0) AS items,
                       COUNT(DISTINCT pt.photo_id) AS photos,
                       COALESCE(SUM(p.deleted_at IS NOT NULL), 0) AS rows_on_soft_deleted,
                       COALESCE(SUM(p.processed_at IS NOT NULL), 0) AS rows_on_processed,
                       COALESCE(SUM(pt.category_litter_object_id IS NOT NULL), 0) AS rows_with_clo,
                       COALESCE(SUM(pt.litter_object_type_id IS NOT NULL), 0) AS rows_typed
                FROM photo_tags pt
                JOIN photos p ON p.id = pt.photo_id
                WHERE pt.litter_object_id = ?
            ', [$objectId]);

            $perObject[$role] = [
                'items' => (int) $row->items,
                'object_id' => (int) $objectId,
                'photos' => (int) $row->photos,
                'rows' => (int) $row->rows_ct,
                'rows_on_processed' => (int) $row->rows_on_processed,
                'rows_on_soft_deleted' => (int) $row->rows_on_soft_deleted,
                'rows_typed' => (int) $row->rows_typed,
                'rows_with_clo' => (int) $row->rows_with_clo,
            ];
        }

        $affectedIds = $this->affectedPhotoIds($e);

        $affected = DB::table('photos')
            ->whereIn('id', $affectedIds)
            ->selectRaw('COUNT(*) AS photos, COALESCE(SUM(xp), 0) AS xp')
            ->first();

        $globalTags = DB::selectOne('
            SELECT COUNT(*) AS rows_ct, COALESCE(SUM(quantity), 0) AS items FROM photo_tags
        ');

        $globalPhotos = DB::selectOne('
            SELECT COUNT(*) AS photos, COALESCE(SUM(xp), 0) AS xp FROM photos WHERE deleted_at IS NULL
        ');

        return [
            'affected' => [
                'photos' => (int) $affected->photos,
                'xp' => (int) $affected->xp,
            ],
            'global' => [
                'photo_tag_items' => (int) $globalTags->items,
                'photo_tag_rows' => (int) $globalTags->rows_ct,
                'photos_live' => (int) $globalPhotos->photos,
                'xp_live' => (int) $globalPhotos->xp,
            ],
            'objects' => $perObject,
        ];
    }

    /** @return array<string, mixed> */
    private function captureStructure(array $e): array
    {
        $pivots = [];

        foreach (DB::table('category_litter_object')
            ->whereIn('litter_object_id', [$e['retired_id'], $e['desired_id']])
            ->orderBy('id')
            ->get() as $clo) {
            $pivots[(string) $clo->id] = [
                'category_id' => (int) $clo->category_id,
                'litter_object_id' => (int) $clo->litter_object_id,
            ];
        }

        $hasRetiredAt = Schema::hasColumn('litter_objects', 'retired_at');
        $objects = [];

        foreach (DB::table('litter_objects')
            ->whereIn('id', [$e['retired_id'], $e['desired_id']])
            ->orderBy('id')
            ->get() as $obj) {
            $objects[(string) $obj->id] = [
                'crowdsourced' => (int) $obj->crowdsourced,
                'key' => $obj->key,
                'merged_into_id' => $hasRetiredAt ? ($obj->merged_into_id ?? null) : null,
                // Presence, never the value. retired_at is stamped with the wall clock at apply
                // time, so recording it would make every after-capture differ and break the
                // determinism contract this file is built on.
                'retired' => $hasRetiredAt && ($obj->retired_at ?? null) !== null,
            ];
        }

        return [
            'litter_objects' => $objects,
            'pivots' => $pivots,
            'schema_has_retired_at' => $hasRetiredAt,
        ];
    }

    /**
     * Decodes summaries for the affected photos and counts which object ids they reference.
     * Decoding in PHP rather than pattern-matching the JSON avoids substring collisions
     * (object_id 92 matching 921) and is exact.
     *
     * @return array<string, mixed>
     */
    private function captureSummaries(array $e): array
    {
        $counts = ['desired' => 0, 'retired' => 0];
        $withoutSummary = 0;
        $ids = [(int) $e['retired_id'] => 'retired', (int) $e['desired_id'] => 'desired'];

        foreach (array_chunk($this->affectedPhotoIds($e), 1000) as $chunk) {
            foreach (DB::table('photos')->whereIn('id', $chunk)->select('summary')->get() as $row) {
                if (empty($row->summary)) {
                    $withoutSummary++;

                    continue;
                }

                $summary = json_decode($row->summary, true);

                if (!is_array($summary)) {
                    $withoutSummary++;

                    continue;
                }

                foreach ($this->objectIdsInSummary($summary) as $objectId) {
                    if (isset($ids[$objectId])) {
                        $counts[$ids[$objectId]]++;
                    }
                }
            }
        }

        return [
            'photos_referencing_desired' => $counts['desired'],
            'photos_referencing_retired' => $counts['retired'],
            'photos_without_usable_summary' => $withoutSummary,
        ];
    }

    /**
     * Every distinct object id a summary references, across both the v5.1 flat and v5.0 nested
     * tag formats plus the keys map.
     *
     * @param  array<string, mixed>  $summary
     * @return array<int, int>
     */
    private function objectIdsInSummary(array $summary): array
    {
        $ids = array_map('intval', array_keys($summary['keys']['objects'] ?? []));
        $tags = $summary['tags'] ?? [];

        if (array_is_list($tags)) {
            foreach ($tags as $tag) {
                if (($tag['object_id'] ?? 0) > 0) {
                    $ids[] = (int) $tag['object_id'];
                }
            }
        } else {
            foreach ($tags as $objects) {
                foreach (array_keys((array) $objects) as $objectId) {
                    $ids[] = (int) $objectId;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Object counts live at every scope in RedisKeys::getPhotoScopes() — global, country,
     * state, city — as both a hash and a ranking ZSET, plus an obj:{id} field in each
     * contributing user's tag hash. Captures all of them, not just global.
     *
     * @return array<string, mixed>
     */
    private function captureRedis(array $e): array
    {
        try {
            $dbsize = (int) Redis::dbsize();
        } catch (Throwable $ex) {
            return ['error' => $ex->getMessage(), 'populated' => false, 'scopes' => [], 'users' => []];
        }

        $scopeRows = DB::table('photos')
            ->whereIn('id', $this->affectedPhotoIds($e))
            ->selectRaw('DISTINCT country_id, state_id, city_id')
            ->get();

        $scopes = [RedisKeys::global()];
        $countries = $states = $cities = [];

        foreach ($scopeRows as $row) {
            if ($row->country_id) {
                $countries[(int) $row->country_id] = true;
            }
            if ($row->state_id) {
                $states[(int) $row->state_id] = true;
            }
            if ($row->city_id) {
                $cities[(int) $row->city_id] = true;
            }
        }

        ksort($countries);
        ksort($states);
        ksort($cities);

        foreach (array_keys($countries) as $id) {
            $scopes[] = RedisKeys::country($id);
        }
        foreach (array_keys($states) as $id) {
            $scopes[] = RedisKeys::state($id);
        }
        foreach (array_keys($cities) as $id) {
            $scopes[] = RedisKeys::city($id);
        }

        $captured = [];

        foreach ($scopes as $scope) {
            $captured[$scope] = [
                'hash_desired' => $this->redisInt(fn () => Redis::hget(RedisKeys::objects($scope), (string) $e['desired_id'])),
                'hash_retired' => $this->redisInt(fn () => Redis::hget(RedisKeys::objects($scope), (string) $e['retired_id'])),
                'rank_desired' => $this->redisInt(fn () => Redis::zscore(RedisKeys::ranking($scope, 'objects'), (string) $e['desired_id'])),
                'rank_retired' => $this->redisInt(fn () => Redis::zscore(RedisKeys::ranking($scope, 'objects'), (string) $e['retired_id'])),
            ];
        }

        $users = [];

        foreach ($this->affectedUserIds($e) as $userId) {
            $scope = RedisKeys::user($userId);
            $users[(string) $userId] = [
                'obj_desired' => $this->redisInt(fn () => Redis::hget("{$scope}:tags", 'obj:' . $e['desired_id'])),
                'obj_retired' => $this->redisInt(fn () => Redis::hget("{$scope}:tags", 'obj:' . $e['retired_id'])),
            ];
        }

        return [
            'populated' => $dbsize > 0,
            'scope_count' => count($scopes),
            'scopes' => $captured,
            'user_count' => count($users),
            'users' => $users,
        ];
    }

    /**
     * ABSOLUTE MySQL↔Redis reconciliation for both objects, at every scope and every user.
     *
     * MySQL truth is scoped exactly as MetricsService counts: processed, not soft-deleted.
     * Only mismatches are recorded, so the artefact stays small and "clean" is unambiguous.
     *
     * Note the two MySQL sources are not the same thing. Redis is replayed from
     * photos.processed_tags, whereas the retirement operates on photo_tags. A divergence
     * between them is pre-existing drift, not a reconciliation failure, so both are reported.
     *
     * @return array<string, mixed>
     */
    private function captureReconciliation(array $e): array
    {
        $objects = ['desired' => (int) $e['desired_id'], 'retired' => (int) $e['retired_id']];
        $mismatches = [];
        $checkedScopes = 0;
        $checkedUsers = 0;

        $levels = [
            'country_id' => fn (int $id): string => RedisKeys::country($id),
            'state_id' => fn (int $id): string => RedisKeys::state($id),
            'city_id' => fn (int $id): string => RedisKeys::city($id),
        ];

        foreach ($objects as $role => $objectId) {
            $globalMysql = (int) DB::table('photo_tags as pt')
                ->join('photos as p', 'p.id', '=', 'pt.photo_id')
                ->where('pt.litter_object_id', $objectId)
                ->whereNotNull('p.processed_at')
                ->whereNull('p.deleted_at')
                ->sum('pt.quantity');

            $scope = RedisKeys::global();
            $redisValue = $this->redisInt(fn () => Redis::hget(RedisKeys::objects($scope), (string) $objectId)) ?? 0;
            $checkedScopes++;

            if ($globalMysql !== $redisValue) {
                $mismatches["{$scope}|{$role}"] = ['mysql' => $globalMysql, 'redis' => $redisValue];
            }

            foreach ($levels as $column => $keyFor) {
                $rows = DB::table('photo_tags as pt')
                    ->join('photos as p', 'p.id', '=', 'pt.photo_id')
                    ->where('pt.litter_object_id', $objectId)
                    ->whereNotNull('p.processed_at')
                    ->whereNull('p.deleted_at')
                    ->whereNotNull("p.{$column}")
                    ->selectRaw("p.{$column} AS scope_id, SUM(pt.quantity) AS items")
                    ->groupBy("p.{$column}")
                    ->orderBy('scope_id')
                    ->get();

                foreach ($rows as $row) {
                    $scope = $keyFor((int) $row->scope_id);
                    $redisValue = $this->redisInt(fn () => Redis::hget(RedisKeys::objects($scope), (string) $objectId)) ?? 0;
                    $checkedScopes++;

                    if ((int) $row->items !== $redisValue) {
                        $mismatches["{$scope}|{$role}"] = ['mysql' => (int) $row->items, 'redis' => $redisValue];
                    }
                }
            }

            $userRows = DB::table('photo_tags as pt')
                ->join('photos as p', 'p.id', '=', 'pt.photo_id')
                ->where('pt.litter_object_id', $objectId)
                ->whereNotNull('p.processed_at')
                ->whereNull('p.deleted_at')
                ->whereNotNull('p.user_id')
                ->selectRaw('p.user_id, SUM(pt.quantity) AS items')
                ->groupBy('p.user_id')
                ->orderBy('p.user_id')
                ->get();

            foreach ($userRows as $row) {
                $userScope = RedisKeys::user((int) $row->user_id);
                $redisValue = $this->redisInt(fn () => Redis::hget("{$userScope}:tags", 'obj:' . $objectId)) ?? 0;
                $checkedUsers++;

                if ((int) $row->items !== $redisValue) {
                    $mismatches["{$userScope}|{$role}"] = ['mysql' => (int) $row->items, 'redis' => $redisValue];
                }
            }
        }

        ksort($mismatches, SORT_STRING);

        return [
            'checked_scopes' => $checkedScopes,
            'checked_users' => $checkedUsers,
            'clean' => $mismatches === [],
            'mismatch_count' => count($mismatches),
            'mismatches' => $mismatches,
            'source_drift' => $this->captureSourceDrift($objects),
        ];
    }

    /**
     * photo_tags vs processed_tags for the two objects, globally. Redis is replayed from the
     * latter, so a gap here explains a reconciliation failure that is not Redis's fault.
     *
     * @param  array<string, int>  $objects
     * @return array<string, mixed>
     */
    private function captureSourceDrift(array $objects): array
    {
        $out = [];

        foreach ($objects as $role => $objectId) {
            $fromTags = (int) DB::table('photo_tags as pt')
                ->join('photos as p', 'p.id', '=', 'pt.photo_id')
                ->where('pt.litter_object_id', $objectId)
                ->whereNotNull('p.processed_at')
                ->whereNull('p.deleted_at')
                ->sum('pt.quantity');

            $fromProcessed = (int) DB::table('photos')
                ->whereNotNull('processed_at')
                ->whereNull('deleted_at')
                ->selectRaw('COALESCE(SUM(CAST(JSON_EXTRACT(processed_tags, ?) AS UNSIGNED)), 0) AS items',
                    ['$.objects."' . $objectId . '"'])
                ->value('items');

            $out[$role] = [
                'photo_tags' => $fromTags,
                'processed_tags' => $fromProcessed,
                'drift' => $fromTags - $fromProcessed,
            ];
        }

        return $out;
    }

    private function redisInt(callable $read): ?int
    {
        try {
            $value = $read();
        } catch (Throwable $e) {
            return null;
        }

        return ($value === false || $value === null) ? null : (int) $value;
    }

    /** @return array<string, mixed> */
    private function captureQuickTags(array $e): array
    {
        $byClo = [];

        foreach (DB::table('user_quick_tags as uqt')
            ->join('category_litter_object as clo', 'clo.id', '=', 'uqt.clo_id')
            ->whereIn('clo.litter_object_id', [$e['retired_id'], $e['desired_id']])
            ->selectRaw('uqt.clo_id, clo.litter_object_id, COUNT(*) AS ct, COUNT(DISTINCT uqt.user_id) AS users')
            ->groupBy('uqt.clo_id', 'clo.litter_object_id')
            ->orderBy('uqt.clo_id')
            ->get() as $row) {
            $byClo[(string) $row->clo_id] = [
                'litter_object_id' => (int) $row->litter_object_id,
                'rows' => (int) $row->ct,
                'users' => (int) $row->users,
            ];
        }

        return ['by_clo' => $byClo];
    }

    /**
     * Reads the tag picker fresh. Neither /api/tags nor /api/tags/all is cached — there is no
     * Cache:: call in GetTagsController, no response-cache package, and no cache middleware on
     * the routes — so calling the controller directly is already a live MySQL read.
     *
     * @return array<string, mixed>
     */
    private function captureApi(array $e): array
    {
        try {
            $response = app(GetTagsController::class)->getAllTags();
            $data = json_decode($response->getContent(), true);
        } catch (Throwable $ex) {
            return ['error' => $ex->getMessage()];
        }

        $keys = [];

        foreach ($data['objects'] ?? $data['litterObjects'] ?? [] as $object) {
            if (isset($object['key'])) {
                $keys[] = $object['key'];
            }
        }

        sort($keys);

        return [
            'desired_key_offered' => in_array($e['desired_key'], $keys, true),
            'object_count' => count($keys),
            'retired_key_offered' => in_array($e['retired_key'], $keys, true),
            'source' => 'GetTagsController::getAllTags (uncached, live MySQL)',
        ];
    }

    /**
     * Export column presence for the two scopes named in the design doc: the reporting team
     * (211) and the heaviest contributing user. Uses the real exporter so the check cannot
     * drift from production column derivation.
     *
     * @return array<string, mixed>
     */
    private function captureExport(array $e): array
    {
        $heaviestUser = DB::table('photo_tags as pt')
            ->join('photos as p', 'p.id', '=', 'pt.photo_id')
            ->whereIn('pt.litter_object_id', [$e['retired_id'], $e['desired_id']])
            ->whereNotNull('p.user_id')
            ->selectRaw('p.user_id, SUM(pt.quantity) AS items')
            ->groupBy('p.user_id')
            ->orderByDesc('items')
            ->orderBy('p.user_id')
            ->first();

        $scopes = [
            'team_211' => ['team_id' => 211, 'user_id' => null],
            'user_heaviest' => ['team_id' => null, 'user_id' => $heaviestUser?->user_id],
        ];

        $out = [];

        foreach ($scopes as $name => $scope) {
            if ($scope['team_id'] === null && $scope['user_id'] === null) {
                $out[$name] = ['skipped' => 'no scope resolved'];

                continue;
            }

            try {
                $export = new CreateCSVExport(null, null, $scope['team_id'], $scope['user_id'], [], [], ['split'], 'wide');
                $headings = $export->headings();
                $flat = is_array($headings[0] ?? null) ? $headings[0] : $headings;
                $flat = array_map('strval', $flat);

                $out[$name] = [
                    'column_count' => count($flat),
                    'desired_column_present' => in_array((string) $e['desired_key'], $flat, true),
                    'retired_column_present' => in_array((string) $e['retired_key'], $flat, true),
                    'scope_id' => $scope['team_id'] ?? $scope['user_id'],
                ];
            } catch (Throwable $ex) {
                $out[$name] = ['error' => $ex->getMessage()];
            }
        }

        return $out;
    }

    // ── Shared populations ───────────────────────────────────────────────────

    /** @var array<int, int>|null */
    private ?array $affectedPhotoIds = null;

    /** @return array<int, int> */
    private function affectedPhotoIds(array $e): array
    {
        if ($this->affectedPhotoIds === null) {
            $this->affectedPhotoIds = DB::table('photo_tags')
                ->whereIn('litter_object_id', [$e['retired_id'], $e['desired_id']])
                ->distinct()
                ->orderBy('photo_id')
                ->pluck('photo_id')
                ->map('intval')
                ->all();
        }

        return $this->affectedPhotoIds;
    }

    /** @return array<int, int> */
    private function affectedUserIds(array $e): array
    {
        return DB::table('photos')
            ->whereIn('id', $this->affectedPhotoIds($e))
            ->whereNotNull('user_id')
            ->distinct()
            ->orderBy('user_id')
            ->pluck('user_id')
            ->map('intval')
            ->all();
    }

    // ── Artefacts ────────────────────────────────────────────────────────────

    private function writeArtefact(array $entry, string $label, array $payload): void
    {
        $disk = Storage::disk('local');
        $dir = self::ARTEFACT_DIR . '/' . $this->option('entry');

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $disk->put("{$dir}/{$label}.json", $json);

        $disk->put("{$dir}/{$label}.meta.json", json_encode([
            'captured_at' => now()->toIso8601String(),
            'database' => config('database.connections.' . config('database.default') . '.database'),
            'label' => $label,
            'sha256' => hash('sha256', $json),
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        $this->newLine();
        $this->info("Artefact: {$dir}/{$label}.json");
        $this->line('  sha256: ' . hash('sha256', $json));
        $this->line('  ' . number_format(strlen($json)) . ' bytes');
        $this->newLine();
        $this->summarise($payload);
    }

    private function summarise(array $p): void
    {
        $m = $p['mysql'];
        $this->line("  retired {$m['objects']['retired']['object_id']}: "
            . number_format($m['objects']['retired']['rows']) . ' rows / '
            . number_format($m['objects']['retired']['items']) . ' items');
        $this->line("  desired {$m['objects']['desired']['object_id']}: "
            . number_format($m['objects']['desired']['rows']) . ' rows / '
            . number_format($m['objects']['desired']['items']) . ' items');
        $this->line('  affected photos: ' . number_format($m['affected']['photos'])
            . ', xp ' . number_format($m['affected']['xp']));
        $this->line('  redis: ' . ($p['redis']['populated'] ? 'populated' : 'EMPTY')
            . ', ' . ($p['redis']['scope_count'] ?? 0) . ' scopes, '
            . ($p['redis']['user_count'] ?? 0) . ' users');
        $this->line('  xp equivalence: ' . ($p['xp_weighting']['equivalent'] ? 'yes' : 'NO')
            . " ({$p['xp_weighting']['retired_xp_per_item']} → {$p['xp_weighting']['desired_xp_per_item']} per item)");

        $r = $p['reconciliation'];
        $this->newLine();

        if ($r['clean']) {
            $this->info("  MySQL↔Redis reconciles ✓  {$r['checked_scopes']} scopes, {$r['checked_users']} user hashes, 0 mismatches");
        } else {
            $this->error("  MySQL↔Redis MISMATCH  {$r['mismatch_count']} of "
                . ($r['checked_scopes'] + $r['checked_users']) . ' checks failed');

            foreach (array_slice($r['mismatches'], 0, 10, true) as $where => $m) {
                $this->line("    {$where}: mysql {$m['mysql']} vs redis {$m['redis']}");
            }
        }

        foreach ($r['source_drift'] as $role => $d) {
            if ($d['drift'] !== 0) {
                $this->warn("  source drift ({$role}): photo_tags {$d['photo_tags']} vs processed_tags {$d['processed_tags']}");
            }
        }
    }

    private function diff(): int
    {
        $entry = $this->resolveEntry();

        if ($entry === null) {
            return self::FAILURE;
        }

        $labels = array_map('trim', explode(',', (string) $this->option('diff')));

        if (count($labels) !== 2) {
            $this->error('--diff needs exactly two labels, e.g. --diff=before,after');

            return self::FAILURE;
        }

        $disk = Storage::disk('local');
        $dir = self::ARTEFACT_DIR . '/' . $this->option('entry');
        $payloads = [];

        foreach ($labels as $label) {
            $path = "{$dir}/{$label}.json";

            if (!$disk->exists($path)) {
                $this->error("Missing artefact: {$path}");

                return self::FAILURE;
            }

            $payloads[$label] = ['raw' => $disk->get($path)];
            $payloads[$label]['data'] = json_decode($payloads[$label]['raw'], true);
        }

        [$a, $b] = $labels;
        $identical = $payloads[$a]['raw'] === $payloads[$b]['raw'];

        $this->newLine();
        $this->line("  {$a}: " . hash('sha256', $payloads[$a]['raw']));
        $this->line("  {$b}: " . hash('sha256', $payloads[$b]['raw']));
        $this->newLine();

        if ($identical) {
            $this->info('  BYTE-IDENTICAL ✓ — no change between captures.');

            return self::SUCCESS;
        }

        $this->warn('  Artefacts differ.');
        $this->newLine();

        $flatBefore = $this->flatten($payloads[$a]['data']);
        $flatAfter = $this->flatten($payloads[$b]['data']);

        foreach ($flatBefore as $path => $before) {
            $after = $flatAfter[$path] ?? null;

            if ($before !== $after) {
                $this->line(sprintf('    %-58s %s → %s', $path,
                    var_export($before, true), var_export($after, true)));
            }
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function flatten(array $data, string $prefix = ''): array
    {
        $out = [];

        foreach ($data as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if (is_array($value)) {
                $out += $this->flatten($value, $path);

                continue;
            }

            $out[$path] = $value;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function deepKsort(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->deepKsort($value);
            }
        }

        ksort($data, SORT_STRING);

        return $data;
    }

    /** @return array<string, int|string>|null */
    private function resolveEntry(): ?array
    {
        $id = (string) $this->option('entry');

        if (!isset(self::RETIREMENTS[$id])) {
            $this->error("Unknown entry: {$id}");
            $this->line('  Known: ' . implode(', ', array_keys(self::RETIREMENTS)));

            return null;
        }

        return self::RETIREMENTS[$id];
    }
}
