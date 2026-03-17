<?php

namespace App\Console\Commands\tmp\v5\Migration\Locations;

use App\Actions\Locations\ResolveLocationAction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LocationCleanupCommand extends Command
{
    protected $signature = 'olm:locations:cleanup
        {--dry-run : Show what would be done without making changes}
        {--skip-orphans : Skip orphan deletion step}
        {--skip-constraints : Skip adding unique constraints}';

    protected $description = 'Merge duplicate locations, remove orphans, and add unique constraints';

    private bool $dryRun = false;
    private int $totalPhotosBefore = 0;
    private int $mergeCount = 0;

    public function handle(): int
    {
        $this->dryRun = $this->option('dry-run');

        if ($this->dryRun) {
            $this->warn('DRY RUN — no changes will be made');
            $this->newLine();
        }

        // Acquire advisory lock to prevent concurrent runs
        if (!$this->dryRun) {
            $lockAcquired = DB::selectOne("SELECT GET_LOCK('olm_location_cleanup', 0) as acquired");
            if (!$lockAcquired->acquired) {
                $this->error('Another instance of this command is already running. Aborting.');
                return self::FAILURE;
            }
        }

        try {
            return $this->runCleanup();
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        } finally {
            if (!$this->dryRun) {
                DB::selectOne("SELECT RELEASE_LOCK('olm_location_cleanup')");
            }
        }
    }

    private function runCleanup(): int
    {
        $this->totalPhotosBefore = DB::table('photos')->count();
        $this->info("Photos before cleanup: " . number_format($this->totalPhotosBefore));
        $this->newLine();

        $this->ensureMergeLogTable();

        // Step 0: Normalize whitespace in location names
        $this->info('═══════════════════════════════════');
        $this->info('Step 0: Normalize whitespace');
        $this->info('═══════════════════════════════════');
        $this->normalizeWhitespace();

        // Step 0.5: Re-resolve error locations
        $this->newLine();
        $this->info('═══════════════════════════════════');
        $this->info('Step 0.5: Re-resolve error locations');
        $this->info('═══════════════════════════════════');
        $this->resolveErrorLocations();
        $this->verifyIntegrity('error location resolution');

        // Step 1: Countries
        $this->info('═══════════════════════════════════');
        $this->info('Step 1: Merge duplicate countries');
        $this->info('═══════════════════════════════════');
        $this->mergeCountries();
        $this->verifyIntegrity('countries');

        // Step 2: States
        $this->newLine();
        $this->info('═══════════════════════════════════');
        $this->info('Step 2: Merge duplicate states');
        $this->info('═══════════════════════════════════');
        $this->mergeStates();
        $this->verifyIntegrity('states');

        // Step 3: Cities
        $this->newLine();
        $this->info('═══════════════════════════════════');
        $this->info('Step 3: Merge duplicate cities');
        $this->info('═══════════════════════════════════');
        $this->mergeCities();
        $this->verifyIntegrity('cities');

        // Step 4: "not found" → Unknown
        $this->newLine();
        $this->info('═══════════════════════════════════');
        $this->info('Step 4: Clean "not found" cities');
        $this->info('═══════════════════════════════════');
        $this->cleanNotFound();
        $this->verifyIntegrity('not-found cleanup');

        // Step 5: Orphans
        if (!$this->option('skip-orphans')) {
            $this->newLine();
            $this->info('═══════════════════════════════════');
            $this->info('Step 5: Delete orphans');
            $this->info('═══════════════════════════════════');
            $this->deleteOrphans();
            $this->verifyIntegrity('orphan deletion');
        }

        // Step 6: Unique constraints
        if (!$this->option('skip-constraints')) {
            $this->newLine();
            $this->info('═══════════════════════════════════');
            $this->info('Step 6: Add unique constraints');
            $this->info('═══════════════════════════════════');
            $this->addUniqueConstraints();
        }

        // Step 7: Tier consistency repair
        $this->newLine();
        $this->info('═══════════════════════════════════');
        $this->info('Step 7: Tier consistency repair');
        $this->info('═══════════════════════════════════');
        $this->repairTierConsistency();
        $this->verifyIntegrity('tier repair');

        // Final verification
        $this->newLine();
        $this->info('═══════════════════════════════════');
        $this->info('Final Verification');
        $this->info('═══════════════════════════════════');
        $this->finalVerification();

        return self::SUCCESS;
    }

    // ─────────────────────────────────────────────
    // Merge Log
    // ─────────────────────────────────────────────

    private function ensureMergeLogTable(): void
    {
        if (!Schema::hasTable('location_merges')) {
            if ($this->dryRun) {
                $this->line('  Would create location_merges table');
                return;
            }

            DB::statement("
                CREATE TABLE location_merges (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    entity_type ENUM('country','state','city') NOT NULL,
                    loser_id INT NOT NULL,
                    keeper_id INT NOT NULL,
                    reason VARCHAR(255),
                    photos_moved INT DEFAULT 0,
                    children_moved INT DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
            $this->info('✓ Created location_merges table');
        }
    }

    private function logMerge(string $type, int $loserId, int $keeperId, string $reason, int $photosMoved, int $childrenMoved = 0): void
    {
        if ($this->dryRun) {
            return;
        }

        DB::table('location_merges')->insert([
            'entity_type' => $type,
            'loser_id' => $loserId,
            'keeper_id' => $keeperId,
            'reason' => $reason,
            'photos_moved' => $photosMoved,
            'children_moved' => $childrenMoved,
        ]);

        $this->mergeCount++;
    }

    // ─────────────────────────────────────────────
    // Step 0: Whitespace normalization
    // ─────────────────────────────────────────────

    private function normalizeWhitespace(): void
    {
        // REGEXP_REPLACE requires MySQL 8+
        $version = DB::selectOne("SELECT VERSION() as v")->v ?? '0';
        $canRegexp = version_compare($version, '8.0.0', '>=');

        if (!$canRegexp) {
            $this->warn('  MySQL < 8 detected — using TRIM only (internal multi-spaces not collapsed)');
        }

        $tables = [
            ['countries', 'country'],
            ['states', 'state'],
            ['cities', 'city'],
        ];

        foreach ($tables as [$table, $column]) {
            if ($canRegexp) {
                $affected = DB::select("
                    SELECT COUNT(*) as cnt FROM {$table}
                    WHERE {$column} != TRIM({$column})
                       OR {$column} REGEXP '  '
                ");
            } else {
                $affected = DB::select("
                    SELECT COUNT(*) as cnt FROM {$table}
                    WHERE {$column} != TRIM({$column})
                ");
            }

            $count = $affected[0]->cnt ?? 0;

            if ($count === 0) {
                $this->info("  ✓ {$table}.{$column} — no whitespace issues");
                continue;
            }

            if ($this->dryRun) {
                $this->line("  [DRY] Would normalize {$count} {$table}.{$column} values");
                continue;
            }

            if ($canRegexp) {
                // TRIM leading/trailing and collapse internal multi-spaces
                DB::update("
                    UPDATE {$table}
                    SET {$column} = TRIM(REGEXP_REPLACE({$column}, '\\\\s+', ' '))
                    WHERE {$column} != TRIM({$column})
                       OR {$column} REGEXP '  '
                ");
            } else {
                // TRIM only — no internal whitespace collapse
                DB::update("
                    UPDATE {$table}
                    SET {$column} = TRIM({$column})
                    WHERE {$column} != TRIM({$column})
                ");
            }

            $this->line("  ✓ Normalized {$count} {$table}.{$column} values");
        }
    }

    // ─────────────────────────────────────────────
    // Step 1: Countries
    // ─────────────────────────────────────────────

    private function mergeCountries(): void
    {
        // Pass 1: Merge by country name
        $this->info('  Pass 1: Merge by country name');
        $this->mergeCountriesByName();

        // Pass 2: Merge by shortcode (catches "United States" vs "USA" etc.)
        $this->info('  Pass 2: Merge by shortcode');
        $this->mergeCountriesByShortcode();
    }

    private function mergeCountriesByName(): void
    {
        $duplicates = DB::select("
            SELECT country, COUNT(*) as cnt
            FROM countries
            GROUP BY country
            HAVING cnt > 1
        ");

        if (empty($duplicates)) {
            $this->info('    No duplicate country names found');
            return;
        }

        foreach ($duplicates as $dup) {
            $candidates = DB::select("
                SELECT c.id, c.country, c.shortcode, c.manual_verify,
                       (SELECT COUNT(*) FROM photos WHERE country_id = c.id) as photo_count,
                       (SELECT COUNT(*) FROM states WHERE country_id = c.id) as child_count
                FROM countries c
                WHERE c.country = ?
                ORDER BY c.manual_verify DESC, photo_count DESC, child_count DESC, c.id ASC
            ", [$dup->country]);

            if (count($candidates) < 2) {
                continue;
            }

            $keeper = $candidates[0];

            for ($i = 1; $i < count($candidates); $i++) {
                $loser = $candidates[$i];

                if (!$this->dryRun) {
                    $exists = DB::table('countries')->where('id', $loser->id)->exists();
                    if (!$exists) {
                        continue;
                    }
                }

                $this->mergeEntity('country', $keeper->id, $loser->id, $keeper->country);
            }
        }
    }

    private function mergeCountriesByShortcode(): void
    {
        $duplicates = DB::select("
            SELECT shortcode, COUNT(*) as cnt
            FROM countries
            WHERE shortcode IS NOT NULL AND shortcode != ''
            GROUP BY shortcode
            HAVING cnt > 1
        ");

        if (empty($duplicates)) {
            $this->info('    No duplicate shortcodes found');
            return;
        }

        $this->info("    Found " . count($duplicates) . " duplicate shortcode group(s)");

        foreach ($duplicates as $dup) {
            $candidates = DB::select("
                SELECT c.id, c.country, c.shortcode, c.manual_verify,
                       (SELECT COUNT(*) FROM photos WHERE country_id = c.id) as photo_count,
                       (SELECT COUNT(*) FROM states WHERE country_id = c.id) as child_count
                FROM countries c
                WHERE c.shortcode = ?
                ORDER BY c.manual_verify DESC, photo_count DESC, child_count DESC, c.id ASC
            ", [$dup->shortcode]);

            if (count($candidates) < 2) {
                continue;
            }

            $keeper = $candidates[0];
            $names = array_map(fn($c) => "\"{$c->country}\"", $candidates);
            $this->info("    Shortcode \"{$dup->shortcode}\": merging " . implode(', ', $names) . " → keeper #{$keeper->id} \"{$keeper->country}\"");

            for ($i = 1; $i < count($candidates); $i++) {
                $loser = $candidates[$i];

                if (!$this->dryRun) {
                    $exists = DB::table('countries')->where('id', $loser->id)->exists();
                    if (!$exists) {
                        continue;
                    }
                }

                $this->mergeEntity('country', $keeper->id, $loser->id, "{$keeper->country} (shortcode:{$dup->shortcode})");
            }
        }
    }

    // ─────────────────────────────────────────────
    // Step 2: States
    // ─────────────────────────────────────────────

    private function mergeStates(): void
    {
        $duplicates = DB::select("
            SELECT country_id, state, COUNT(*) as cnt
            FROM states
            GROUP BY country_id, state
            HAVING cnt > 1
        ");

        if (empty($duplicates)) {
            $this->info('  No duplicate states found');
            return;
        }

        foreach ($duplicates as $dup) {
            $candidates = DB::select("
                SELECT s.id, s.state, s.country_id, s.manual_verify,
                       (SELECT COUNT(*) FROM photos WHERE state_id = s.id) as photo_count,
                       (SELECT COUNT(*) FROM cities WHERE state_id = s.id) as child_count
                FROM states s
                WHERE s.country_id = ? AND s.state = ?
                ORDER BY s.manual_verify DESC, photo_count DESC, child_count DESC, s.id ASC
            ", [$dup->country_id, $dup->state]);

            if (count($candidates) < 2) {
                continue;
            }

            $keeper = $candidates[0];

            for ($i = 1; $i < count($candidates); $i++) {
                $loser = $candidates[$i];

                if (!$this->dryRun) {
                    $exists = DB::table('states')->where('id', $loser->id)->exists();
                    if (!$exists) {
                        continue;
                    }
                }

                $this->mergeEntity('state', $keeper->id, $loser->id, $keeper->state);
            }
        }
    }

    // ─────────────────────────────────────────────
    // Step 3: Cities
    // ─────────────────────────────────────────────

    private function mergeCities(): void
    {
        $duplicates = DB::select("
            SELECT state_id, city, COUNT(*) as cnt
            FROM cities
            GROUP BY state_id, city
            HAVING cnt > 1
            ORDER BY cnt DESC
        ");

        if (empty($duplicates)) {
            $this->info('  No duplicate cities found');
            return;
        }

        $this->info("  Found " . count($duplicates) . " duplicate city groups");

        foreach ($duplicates as $dup) {
            $candidates = DB::select("
                SELECT ci.id, ci.city, ci.state_id, ci.manual_verify,
                       (SELECT COUNT(*) FROM photos WHERE city_id = ci.id) as photo_count
                FROM cities ci
                WHERE ci.state_id = ? AND ci.city = ?
                ORDER BY ci.manual_verify DESC, photo_count DESC, ci.id ASC
            ", [$dup->state_id, $dup->city]);

            if (count($candidates) < 2) {
                continue;
            }

            $keeper = $candidates[0];
            $losersInGroup = count($candidates) - 1;

            if ($dup->cnt > 2) {
                $this->info("  Merging {$dup->cnt} \"{$dup->city}\" records (state_id={$dup->state_id}) → keeper #{$keeper->id}");
            }

            for ($i = 1; $i < count($candidates); $i++) {
                $loser = $candidates[$i];

                if (!$this->dryRun) {
                    $exists = DB::table('cities')->where('id', $loser->id)->exists();
                    if (!$exists) {
                        continue;
                    }
                }

                $this->mergeEntity('city', $keeper->id, $loser->id, $keeper->city);
            }
        }
    }

    // ─────────────────────────────────────────────
    // Generic merge logic
    // ─────────────────────────────────────────────

    private function mergeEntity(string $type, int $keeperId, int $loserId, string $name): void
    {
        $photoColumn = match ($type) {
            'country' => 'country_id',
            'state' => 'state_id',
            'city' => 'city_id',
        };

        // Count what we're moving
        $photosToMove = DB::table('photos')->where($photoColumn, $loserId)->count();

        $childrenToMove = 0;
        if ($type === 'country') {
            $childrenToMove += DB::table('states')->where('country_id', $loserId)->count();
            $childrenToMove += DB::table('cities')->where('country_id', $loserId)->count();
        } elseif ($type === 'state') {
            $childrenToMove += DB::table('cities')->where('state_id', $loserId)->count();
        }

        $label = $photosToMove > 0 || $childrenToMove > 0
            ? "({$photosToMove} photos, {$childrenToMove} children)"
            : "(empty)";

        if ($this->dryRun) {
            $this->line("  [DRY] Would merge {$type} #{$loserId} → #{$keeperId} \"{$name}\" {$label}");
            return;
        }

        DB::transaction(function () use ($type, $keeperId, $loserId, $photoColumn, $photosToMove, $childrenToMove, $name) {
            // Move photos
            if ($photosToMove > 0) {
                DB::table('photos')
                    ->where($photoColumn, $loserId)
                    ->update([$photoColumn => $keeperId]);
            }

            // Move children — merge collisions first to avoid unique constraint violations
            if ($type === 'country') {
                $this->mergeChildrenOnCountryMerge($keeperId, $loserId);
            } elseif ($type === 'state') {
                $this->mergeChildrenOnStateMerge($keeperId, $loserId);
            }

            // Delete loser
            $table = match ($type) {
                'country' => 'countries',
                'state' => 'states',
                'city' => 'cities',
            };

            DB::table($table)->where('id', $loserId)->delete();

            // Log merge
            $this->logMerge($type, $loserId, $keeperId, "Duplicate \"{$name}\"", $photosToMove, $childrenToMove);
        });

        $this->line("  ✓ Merged {$type} #{$loserId} → #{$keeperId} \"{$name}\" {$label}");
    }

    /**
     * When merging two countries, loop through the loser's states.
     * For each, find the original (first-created) in the keeper country.
     * If it exists, merge the duplicate state's cities and photos into the original.
     * If not, just move it across.
     */
    private function mergeChildrenOnCountryMerge(int $keeperCountryId, int $loserCountryId): void
    {
        $loserStates = DB::table('states')
            ->where('country_id', $loserCountryId)
            ->get();

        foreach ($loserStates as $loserState) {
            // Does the keeper country already have a state with this name?
            $original = DB::table('states')
                ->where('country_id', $keeperCountryId)
                ->where('state', $loserState->state)
                ->orderBy('id')
                ->first();

            if ($original) {
                // Original exists — merge this duplicate state into it
                $this->mergeChildState($original->id, $loserState->id, $loserState->state);
            } else {
                // No collision — just move the state to the keeper country
                DB::table('states')->where('id', $loserState->id)->update(['country_id' => $keeperCountryId]);
            }
        }

        // Move any cities still referencing the loser country
        DB::table('cities')->where('country_id', $loserCountryId)->update(['country_id' => $keeperCountryId]);
    }

    /**
     * Merge a duplicate state into the original.
     * First handles city collisions within, then moves remaining cities, then deletes.
     */
    private function mergeChildState(int $originalStateId, int $duplicateStateId, string $stateName): void
    {
        // Move photos pointing at the duplicate state to the original
        $photosMoved = DB::table('photos')->where('state_id', $duplicateStateId)->count();
        if ($photosMoved > 0) {
            DB::table('photos')->where('state_id', $duplicateStateId)->update(['state_id' => $originalStateId]);
        }

        // Merge cities within — same pattern
        $this->mergeChildrenOnStateMerge($originalStateId, $duplicateStateId);

        // Delete the now-empty duplicate state
        DB::table('states')->where('id', $duplicateStateId)->delete();

        $this->logMerge('state', $duplicateStateId, $originalStateId, "Child merge during country merge: \"{$stateName}\"", $photosMoved);
        $this->line("    ↳ Merged child state #{$duplicateStateId} → #{$originalStateId} \"{$stateName}\" ({$photosMoved} photos)");
    }

    /**
     * When merging two states, loop through the loser's cities.
     * For each, find the original (first-created) in the keeper state.
     * If it exists, move the duplicate's photos to the original and delete it.
     * If not, just move the city across.
     */
    private function mergeChildrenOnStateMerge(int $originalStateId, int $duplicateStateId): void
    {
        $duplicateCities = DB::table('cities')
            ->where('state_id', $duplicateStateId)
            ->get();

        foreach ($duplicateCities as $dupCity) {
            // Does the original state already have a city with this name?
            $original = DB::table('cities')
                ->where('state_id', $originalStateId)
                ->where('city', $dupCity->city)
                ->orderBy('id')
                ->first();

            if ($original) {
                // Original exists — move photos and delete duplicate
                $photosMoved = DB::table('photos')->where('city_id', $dupCity->id)->count();
                if ($photosMoved > 0) {
                    DB::table('photos')->where('city_id', $dupCity->id)->update(['city_id' => $original->id]);
                }

                DB::table('cities')->where('id', $dupCity->id)->delete();

                $this->logMerge('city', $dupCity->id, $original->id, "Child merge during state merge: \"{$dupCity->city}\"", $photosMoved);
                $this->line("    ↳ Merged child city #{$dupCity->id} → #{$original->id} \"{$dupCity->city}\" ({$photosMoved} photos)");
            } else {
                // No collision — just move the city to the original state
                DB::table('cities')->where('id', $dupCity->id)->update(['state_id' => $originalStateId]);
            }
        }
    }

    // ─────────────────────────────────────────────
    // Step 4: "not found" → Unknown
    // ─────────────────────────────────────────────

    private function cleanNotFound(): void
    {
        $notFoundCities = DB::select("
            SELECT state_id, COUNT(*) as cnt
            FROM cities
            WHERE city = 'not found'
            GROUP BY state_id
        ");

        if (empty($notFoundCities)) {
            $this->info('  No "not found" cities');
            return;
        }

        foreach ($notFoundCities as $group) {
            $stateId = $group->state_id;
            $count = $group->cnt;

            // Find or use the first one as keeper, rename to "Unknown"
            $keeper = DB::table('cities')
                ->where('city', 'not found')
                ->where('state_id', $stateId)
                ->orderBy('id')
                ->first();

            if (!$keeper) {
                continue;
            }

            // Also check if an "Unknown" city already exists in this state
            $existingUnknown = DB::table('cities')
                ->where('city', 'Unknown')
                ->where('state_id', $stateId)
                ->first();

            $targetId = $existingUnknown ? $existingUnknown->id : $keeper->id;

            if ($this->dryRun) {
                $this->line("  [DRY] Would merge {$count} \"not found\" cities in state_id={$stateId} → #{$targetId} \"Unknown\"");
                continue;
            }

            DB::transaction(function () use ($stateId, $targetId, $keeper, $existingUnknown, $count) {
                // Move all photos from "not found" cities to target
                $loserIds = DB::table('cities')
                    ->where('city', 'not found')
                    ->where('state_id', $stateId)
                    ->where('id', '!=', $targetId)
                    ->pluck('id')
                    ->toArray();

                // If keeper IS the target (no existing Unknown), rename it
                if (!$existingUnknown) {
                    DB::table('cities')->where('id', $targetId)->update(['city' => 'Unknown']);
                }

                if (!empty($loserIds)) {
                    foreach ($loserIds as $loserId) {
                        $photosMoved = DB::table('photos')
                            ->where('city_id', $loserId)
                            ->count();

                        DB::table('photos')
                            ->where('city_id', $loserId)
                            ->update(['city_id' => $targetId]);

                        $this->logMerge('city', $loserId, $targetId, "\"not found\" → Unknown in state_id={$stateId}", $photosMoved);
                    }

                    DB::table('cities')
                        ->whereIn('id', $loserIds)
                        ->delete();
                } elseif (!$existingUnknown) {
                    // Just the rename, no merging needed — log as rename
                    $this->logMerge('city', $keeper->id, $targetId, "Renamed \"not found\" → \"Unknown\"", 0);
                }
            });

            $this->line("  ✓ Cleaned {$count} \"not found\" → Unknown in state_id={$stateId}");
        }
    }

    // ─────────────────────────────────────────────
    // Step 5: Orphans
    // ─────────────────────────────────────────────

    private function deleteOrphans(): void
    {
        // Orphaned cities (no photos)
        $orphanedCities = DB::select("
            SELECT COUNT(*) as cnt FROM cities c
            WHERE NOT EXISTS (SELECT 1 FROM photos p WHERE p.city_id = c.id)
        ");
        $cityCount = $orphanedCities[0]->cnt ?? 0;

        // Orphaned states (no photos AND no remaining cities)
        $orphanedStates = DB::select("
            SELECT COUNT(*) as cnt FROM states s
            WHERE NOT EXISTS (SELECT 1 FROM photos p WHERE p.state_id = s.id)
            AND NOT EXISTS (SELECT 1 FROM cities c WHERE c.state_id = s.id)
        ");
        $stateCount = $orphanedStates[0]->cnt ?? 0;

        $this->info("  Orphaned cities: {$cityCount}");
        $this->info("  Orphaned states (no photos, no cities): {$stateCount}");

        if ($cityCount === 0 && $stateCount === 0) {
            $this->info('  Nothing to delete');
            return;
        }

        if ($this->dryRun) {
            $this->line("  [DRY] Would delete {$cityCount} orphaned cities and {$stateCount} orphaned states");
            return;
        }

        // Delete cities first (they reference states)
        if ($cityCount > 0) {
            $deleted = DB::delete("
                DELETE c FROM cities c
                LEFT JOIN photos p ON p.city_id = c.id
                WHERE p.id IS NULL
            ");
            $this->line("  ✓ Deleted {$deleted} orphaned cities");
        }

        // Then states (only those with no photos AND no remaining cities)
        if ($stateCount > 0) {
            $deleted = DB::delete("
                DELETE s FROM states s
                LEFT JOIN photos p ON p.state_id = s.id
                LEFT JOIN cities c ON c.state_id = s.id
                WHERE p.id IS NULL AND c.id IS NULL
            ");
            $this->line("  ✓ Deleted {$deleted} orphaned states");
        }
    }

    // ─────────────────────────────────────────────
    // Step 6: Unique constraints
    // ─────────────────────────────────────────────

    private function addUniqueConstraints(): void
    {
        $constraints = [
            ['countries', 'uq_country_shortcode', 'ALTER TABLE countries ADD UNIQUE INDEX uq_country_shortcode (shortcode)'],
            ['states', 'uq_state_country', 'ALTER TABLE states ADD UNIQUE INDEX uq_state_country (country_id, state)'],
            ['cities', 'uq_city_state', 'ALTER TABLE cities ADD UNIQUE INDEX uq_city_state (state_id, city)'],
        ];

        foreach ($constraints as [$table, $indexName, $sql]) {
            // Check if index already exists
            $existing = DB::select("
                SHOW INDEX FROM {$table} WHERE Key_name = ?
            ", [$indexName]);

            if (!empty($existing)) {
                $this->info("  ✓ {$indexName} already exists");
                continue;
            }

            // Verify no remaining duplicates before adding constraint
            $dupeCheck = match ($table) {
                'countries' => DB::select("SELECT shortcode, COUNT(*) as cnt FROM countries GROUP BY shortcode HAVING cnt > 1"),
                'states' => DB::select("SELECT country_id, state, COUNT(*) as cnt FROM states GROUP BY country_id, state HAVING cnt > 1"),
                'cities' => DB::select("SELECT state_id, city, COUNT(*) as cnt FROM cities GROUP BY state_id, city HAVING cnt > 1"),
            };

            if (!empty($dupeCheck)) {
                $this->error("  ✗ Cannot add {$indexName} — " . count($dupeCheck) . " duplicate group(s) remain!");
                foreach ($dupeCheck as $d) {
                    $this->error("    " . json_encode($d));
                }
                continue;
            }

            if ($this->dryRun) {
                $this->line("  [DRY] Would add {$indexName}");
                continue;
            }

            DB::statement($sql);
            $this->line("  ✓ Added {$indexName}");
        }
    }

    // ─────────────────────────────────────────────
    // Step 7: Tier consistency repair
    // ─────────────────────────────────────────────

    private function repairTierConsistency(): void
    {
        // Canonical source of truth: state → city → photo (top-down validation).
        // First fix broken references in the location tables themselves,
        // then propagate correct values to photos.

        // Pass 0: Detect cities referencing non-existent states
        $brokenCities = DB::selectOne("
            SELECT COUNT(*) as cnt
            FROM cities c
            LEFT JOIN states s ON s.id = c.state_id
            WHERE s.id IS NULL
        ");
        $brokenCityCount = $brokenCities->cnt ?? 0;

        if ($brokenCityCount > 0) {
            $this->warn("  ⚠ {$brokenCityCount} cities reference non-existent states — these photos will be skipped");

            // Log the affected photo count for visibility
            $affectedPhotos = DB::selectOne("
                SELECT COUNT(*) as cnt
                FROM photos p
                JOIN cities c ON c.id = p.city_id
                LEFT JOIN states s ON s.id = c.state_id
                WHERE s.id IS NULL
            ");
            $this->warn("  ⚠ {$affectedPhotos->cnt} photos affected by broken city→state references");
        }

        // Pass 1: Fix cities.country_id to match their state's country_id
        // (only for cities that have a valid state reference)
        $cityTableMismatch = DB::selectOne("
            SELECT COUNT(*) as cnt
            FROM cities c
            JOIN states s ON s.id = c.state_id
            WHERE c.country_id != s.country_id
        ");

        $cityTableCount = $cityTableMismatch->cnt ?? 0;

        if ($cityTableCount > 0) {
            if ($this->dryRun) {
                $this->line("  [DRY] Would fix {$cityTableCount} cities where country_id doesn't match state's country_id");
            } else {
                DB::update("
                    UPDATE cities c
                    JOIN states s ON s.id = c.state_id
                    SET c.country_id = s.country_id
                    WHERE c.country_id != s.country_id
                ");
                $this->line("  ✓ Fixed {$cityTableCount} cities: country_id aligned with state's country_id");
            }
        } else {
            $this->info('  ✓ All cities.country_id consistent with states');
        }

        // Pass 2: Fix photos.state_id from their city's state_id
        // JOIN states to validate the city's state_id actually exists (FK safety)
        $photoStateMismatch = DB::selectOne("
            SELECT COUNT(*) as cnt
            FROM photos p
            JOIN cities c ON c.id = p.city_id
            JOIN states s ON s.id = c.state_id
            WHERE p.state_id != c.state_id
        ");

        $photoStateCount = $photoStateMismatch->cnt ?? 0;

        if ($photoStateCount > 0) {
            if ($this->dryRun) {
                $this->line("  [DRY] Would fix {$photoStateCount} photos where state_id doesn't match city's state_id");
            } else {
                DB::update("
                    UPDATE photos p
                    JOIN cities c ON c.id = p.city_id
                    JOIN states s ON s.id = c.state_id
                    SET p.state_id = c.state_id
                    WHERE p.state_id != c.state_id
                ");
                $this->line("  ✓ Fixed {$photoStateCount} photos: state_id aligned with city's state_id");
            }
        } else {
            $this->info('  ✓ All photos.state_id consistent with cities');
        }

        // Pass 3: Fix photos.country_id from their city's country_id
        // (city.country_id is now guaranteed correct from Pass 1)
        // JOIN states to validate the full chain
        $photoCountryMismatch = DB::selectOne("
            SELECT COUNT(*) as cnt
            FROM photos p
            JOIN cities c ON c.id = p.city_id
            JOIN states s ON s.id = c.state_id
            WHERE p.country_id != c.country_id
        ");

        $photoCountryCount = $photoCountryMismatch->cnt ?? 0;

        if ($photoCountryCount > 0) {
            if ($this->dryRun) {
                $this->line("  [DRY] Would fix {$photoCountryCount} photos where country_id doesn't match city's country_id");
            } else {
                DB::update("
                    UPDATE photos p
                    JOIN cities c ON c.id = p.city_id
                    JOIN states s ON s.id = c.state_id
                    SET p.country_id = c.country_id
                    WHERE p.country_id != c.country_id
                ");
                $this->line("  ✓ Fixed {$photoCountryCount} photos: country_id aligned with city's country_id");
            }
        } else {
            $this->info('  ✓ All photos.country_id consistent with cities');
        }
    }

    // ─────────────────────────────────────────────
    // Step 0.5: Error location resolution
    // ─────────────────────────────────────────────

    private const ERROR_COUNTRY_ID = 16;
    private const ERROR_STATE_ID = 46;
    private const ERROR_CITY_ID = 89;

    /**
     * Re-resolve photos assigned to the error_country/error_state/error_city
     * sentinel locations from their stored address_array (no API needed).
     *
     * Photos without address_array should be pre-fixed by running
     * `olm:locations:resolve-errors` before the migration.
     * After this step, error locations become orphans and are cleaned up in Step 5.
     */
    private function resolveErrorLocations(): void
    {
        $errorCountry = DB::table('countries')
            ->where('id', self::ERROR_COUNTRY_ID)
            ->first();

        if (!$errorCountry || $errorCountry->country !== 'error_country') {
            $this->info('  No error_country (id=16) found — skipping');
            return;
        }

        $total = DB::table('photos')
            ->where('country_id', self::ERROR_COUNTRY_ID)
            ->whereNull('deleted_at')
            ->count();

        if ($total === 0) {
            $this->info('  No photos linked to error_country');
            return;
        }

        $this->info("  Found {$total} photos linked to error_country");

        if ($this->dryRun) {
            $this->line("  [DRY] Would re-resolve photos from stored address_array");
            return;
        }

        // Resolve from stored address_array (offline, no API)
        $resolved = $this->resolveErrorPhotosFromAddress();
        $this->info("  ✓ {$resolved} photos resolved from address_array");

        // Report remaining
        $remaining = DB::table('photos')
            ->where('country_id', self::ERROR_COUNTRY_ID)
            ->whereNull('deleted_at')
            ->count();

        if ($remaining > 0) {
            $this->warn("  ⚠ {$remaining} photos still on error_country (no address_array — run olm:locations:resolve-errors to fix via API)");
        } else {
            $this->info("  ✓ All error_country photos resolved");
        }
    }

    /**
     * Phase 1: Re-resolve photos that have a stored address_array with country_code.
     */
    private function resolveErrorPhotosFromAddress(): int
    {
        $resolved = 0;

        DB::table('photos')
            ->where('country_id', self::ERROR_COUNTRY_ID)
            ->whereNotNull('address_array')
            ->where('address_array', '!=', '')
            ->where('address_array', '!=', 'null')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunk(200, function ($photos) use (&$resolved) {
                foreach ($photos as $photo) {
                    $address = json_decode($photo->address_array, true);

                    if (!$address || empty($address['country_code'])) {
                        continue;
                    }

                    $location = $this->resolveFromAddress($address);

                    if ($location) {
                        DB::table('photos')
                            ->where('id', $photo->id)
                            ->update([
                                'country_id' => $location['country_id'],
                                'state_id' => $location['state_id'],
                                'city_id' => $location['city_id'],
                            ]);
                        $resolved++;
                    }
                }
            });

        return $resolved;
    }

    /**
     * Resolve country/state/city from an address array using the same
     * lookup logic as ResolveLocationAction, but via raw DB queries
     * (no auth user needed, no API call).
     */
    private function resolveFromAddress(array $address): ?array
    {
        $countryCode = strtoupper($address['country_code']);
        $countryName = $address['country'] ?? '';

        // Resolve country
        $country = DB::table('countries')
            ->where('shortcode', $countryCode)
            ->first();

        if (!$country) {
            $country = (object) [
                'id' => DB::table('countries')->insertGetId([
                    'shortcode' => $countryCode,
                    'country' => $countryName,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]),
            ];
        }

        // Resolve state (same fallback chain as ResolveLocationAction::STATE_KEYS)
        $stateKeys = ResolveLocationAction::STATE_KEYS;
        $stateName = null;
        foreach ($stateKeys as $key) {
            if (!empty($address[$key])) {
                $stateName = $address[$key];
                break;
            }
        }

        $state = null;
        if ($stateName) {
            $state = DB::table('states')
                ->where('country_id', $country->id)
                ->where('state', $stateName)
                ->first();

            if (!$state) {
                $state = (object) [
                    'id' => DB::table('states')->insertGetId([
                        'state' => $stateName,
                        'country_id' => $country->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]),
                ];
            }
        }

        // Resolve city (same fallback chain as ResolveLocationAction::CITY_KEYS)
        $cityKeys = ResolveLocationAction::CITY_KEYS;
        $cityName = null;
        foreach ($cityKeys as $key) {
            if (!empty($address[$key])) {
                $cityName = $address[$key];
                break;
            }
        }

        $city = null;
        if ($cityName && $state) {
            $city = DB::table('cities')
                ->where('state_id', $state->id)
                ->where('city', $cityName)
                ->first();

            if (!$city) {
                $city = (object) [
                    'id' => DB::table('cities')->insertGetId([
                        'city' => $cityName,
                        'state_id' => $state->id,
                        'country_id' => $country->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]),
                ];
            }
        }

        return [
            'country_id' => $country->id,
            'state_id' => $state?->id,
            'city_id' => $city?->id,
        ];
    }

    // ─────────────────────────────────────────────
    // Verification
    // ─────────────────────────────────────────────

    private function verifyIntegrity(string $afterStep): void
    {
        if ($this->dryRun) {
            return;
        }

        $currentPhotos = DB::table('photos')->count();

        if ($currentPhotos !== $this->totalPhotosBefore) {
            $this->error("  ✗ PHOTO COUNT CHANGED after {$afterStep}! Before: {$this->totalPhotosBefore}, Now: {$currentPhotos}");
            throw new \RuntimeException("ABORTING — photo count changed after {$afterStep}");
        }

        // Check broken FKs (state_id and city_id are nullable)
        $broken = DB::select("
            SELECT 'country' as type, COUNT(*) as cnt FROM photos p
            WHERE NOT EXISTS (SELECT 1 FROM countries c WHERE c.id = p.country_id)
            UNION ALL
            SELECT 'state', COUNT(*) FROM photos p
            WHERE p.state_id IS NOT NULL
            AND NOT EXISTS (SELECT 1 FROM states s WHERE s.id = p.state_id)
            UNION ALL
            SELECT 'city', COUNT(*) FROM photos p
            WHERE p.city_id IS NOT NULL
            AND NOT EXISTS (SELECT 1 FROM cities c WHERE c.id = p.city_id)
        ");

        $hasBroken = false;
        foreach ($broken as $b) {
            if ($b->cnt > 0) {
                $this->error("  ✗ {$b->cnt} photos with broken {$b->type}_id after {$afterStep}!");
                $hasBroken = true;
            }
        }

        if ($hasBroken) {
            throw new \RuntimeException("ABORTING — broken foreign keys detected after {$afterStep}");
        }

        // Check tier consistency (photo's country matches state's country, etc.)
        $tierMismatch = DB::select("
            SELECT 'state_country' as type, COUNT(*) as cnt
            FROM photos p JOIN states s ON s.id = p.state_id
            WHERE p.country_id != s.country_id
            UNION ALL
            SELECT 'city_state', COUNT(*)
            FROM photos p JOIN cities c ON c.id = p.city_id
            WHERE p.state_id != c.state_id
            UNION ALL
            SELECT 'city_country', COUNT(*)
            FROM photos p JOIN cities c ON c.id = p.city_id
            WHERE p.country_id != c.country_id
        ");

        $hasMismatch = false;
        foreach ($tierMismatch as $m) {
            if ($m->cnt > 0) {
                $this->warn("  ⚠ {$m->cnt} photos with {$m->type} tier mismatch after {$afterStep}");
                $hasMismatch = true;
            }
        }

        if (!$hasMismatch) {
            $this->info("  ✓ Tier consistency check passed after {$afterStep}");
        }

        $this->info("  ✓ Integrity check passed after {$afterStep}");
    }

    private function finalVerification(): void
    {
        if ($this->dryRun) {
            $this->info('  Dry run complete — no changes were made');
            $this->newLine();
            return;
        }

        $currentPhotos = DB::table('photos')->count();
        $countries = DB::table('countries')->count();
        $states = DB::table('states')->count();
        $cities = DB::table('cities')->count();

        // Check for remaining duplicates
        $dupCountries = DB::select("SELECT COUNT(*) as cnt FROM (SELECT country FROM countries GROUP BY country HAVING COUNT(*) > 1) t");
        $dupStates = DB::select("SELECT COUNT(*) as cnt FROM (SELECT country_id, state FROM states GROUP BY country_id, state HAVING COUNT(*) > 1) t");
        $dupCities = DB::select("SELECT COUNT(*) as cnt FROM (SELECT state_id, city FROM cities GROUP BY state_id, city HAVING COUNT(*) > 1) t");

        $orphanStates = DB::select("SELECT COUNT(*) as cnt FROM states s WHERE NOT EXISTS (SELECT 1 FROM photos p WHERE p.state_id = s.id)");
        $orphanCities = DB::select("SELECT COUNT(*) as cnt FROM cities c WHERE NOT EXISTS (SELECT 1 FROM photos p WHERE p.city_id = c.id)");

        // Tier consistency
        $tierMismatch = DB::selectOne("
            SELECT
                (SELECT COUNT(*) FROM photos p JOIN states s ON s.id = p.state_id WHERE p.country_id != s.country_id) as state_country,
                (SELECT COUNT(*) FROM photos p JOIN cities c ON c.id = p.city_id WHERE p.state_id != c.state_id) as city_state,
                (SELECT COUNT(*) FROM photos p JOIN cities c ON c.id = p.city_id WHERE p.country_id != c.country_id) as city_country
        ");
        $totalMismatch = ($tierMismatch->state_country ?? 0) + ($tierMismatch->city_state ?? 0) + ($tierMismatch->city_country ?? 0);

        $this->table(
            ['Metric', 'Value'],
            [
                ['Photos (should be unchanged)', number_format($currentPhotos) . ($currentPhotos === $this->totalPhotosBefore ? ' ✓' : ' ✗ MISMATCH')],
                ['Countries', number_format($countries)],
                ['States', number_format($states)],
                ['Cities', number_format($cities)],
                ['Remaining duplicate countries', $dupCountries[0]->cnt . ($dupCountries[0]->cnt == 0 ? ' ✓' : ' ✗')],
                ['Remaining duplicate states', $dupStates[0]->cnt . ($dupStates[0]->cnt == 0 ? ' ✓' : ' ✗')],
                ['Remaining duplicate cities', $dupCities[0]->cnt . ($dupCities[0]->cnt == 0 ? ' ✓' : ' ✗')],
                ['Tier mismatches', $totalMismatch . ($totalMismatch == 0 ? ' ✓' : ' ✗')],
                ['Orphaned states', $orphanStates[0]->cnt],
                ['Orphaned cities', $orphanCities[0]->cnt],
                ['Total merges performed', $this->mergeCount],
            ]
        );
    }
}
