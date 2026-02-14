<?php

namespace App\Console\Commands\tmp\v5\Migration;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LocationAnalysisCommand extends Command
{
    protected $signature = 'olm:locations:analysis';
    protected $description = 'Analyze location data integrity — duplicates, orphans, tier consistency';

    public function handle(): int
    {
        $this->info('═══════════════════════════════════════════════');
        $this->info('  OpenLitterMap Location Analysis');
        $this->info('═══════════════════════════════════════════════');
        $this->newLine();

        // ── Totals ──
        $photos    = DB::table('photos')->count();
        $countries = DB::table('countries')->count();
        $states    = DB::table('states')->count();
        $cities    = DB::table('cities')->count();

        $this->table(['Entity', 'Count'], [
            ['Photos', number_format($photos)],
            ['Countries', number_format($countries)],
            ['States', number_format($states)],
            ['Cities', number_format($cities)],
        ]);

        // ── 1. Duplicate Countries (by name) ──
        $this->newLine();
        $this->info('── Duplicate Countries (by name) ──');

        $dupCountriesByName = DB::select("
            SELECT c.country, COUNT(*) as cnt,
                   GROUP_CONCAT(c.id ORDER BY c.id SEPARATOR ', ') as ids,
                   (SELECT COUNT(*) FROM photos p WHERE p.country_id IN (
                       SELECT c2.id FROM countries c2 WHERE c2.country = c.country
                   )) as total_photos
            FROM countries c
            GROUP BY c.country
            HAVING cnt > 1
            ORDER BY cnt DESC
        ");

        if (empty($dupCountriesByName)) {
            $this->info('  None ✓');
        } else {
            $rows = array_map(fn($d) => [$d->country, $d->cnt, $d->ids, number_format($d->total_photos)], $dupCountriesByName);
            $this->table(['Country Name', 'Duplicates', 'IDs', 'Photos Affected'], $rows);
        }

        // ── 2. Duplicate Countries (by shortcode) ──
        $this->newLine();
        $this->info('── Duplicate Countries (by shortcode) ──');

        $dupCountriesByCode = DB::select("
            SELECT c.shortcode, COUNT(*) as cnt,
                   GROUP_CONCAT(CONCAT(c.id, ':', c.country) ORDER BY c.id SEPARATOR ' | ') as entries,
                   (SELECT COUNT(*) FROM photos p WHERE p.country_id IN (
                       SELECT c2.id FROM countries c2 WHERE c2.shortcode = c.shortcode
                   )) as total_photos
            FROM countries c
            WHERE c.shortcode IS NOT NULL AND c.shortcode != ''
            GROUP BY c.shortcode
            HAVING cnt > 1
            ORDER BY total_photos DESC
        ");

        if (empty($dupCountriesByCode)) {
            $this->info('  None ✓');
        } else {
            $rows = array_map(fn($d) => [$d->shortcode, $d->cnt, $d->entries, number_format($d->total_photos)], $dupCountriesByCode);
            $this->table(['Shortcode', 'Duplicates', 'Entries (id:name)', 'Photos Affected'], $rows);
        }

        // ── 3. Duplicate States (by country_id + state name) ──
        $this->newLine();
        $this->info('── Duplicate States (by country_id + state name) ──');

        $dupStates = DB::select("
            SELECT s.country_id, co.country as country_name, s.state, COUNT(*) as cnt,
                   GROUP_CONCAT(s.id ORDER BY s.id SEPARATOR ', ') as ids,
                   (SELECT COUNT(*) FROM photos p WHERE p.state_id IN (
                       SELECT s2.id FROM states s2 WHERE s2.country_id = s.country_id AND s2.state = s.state
                   )) as total_photos
            FROM states s
            LEFT JOIN countries co ON co.id = s.country_id
            GROUP BY s.country_id, s.state
            HAVING cnt > 1
            ORDER BY total_photos DESC
            LIMIT 30
        ");

        if (empty($dupStates)) {
            $this->info('  None ✓');
        } else {
            $rows = array_map(fn($d) => [$d->country_name, $d->state, $d->cnt, $d->ids, number_format($d->total_photos)], $dupStates);
            $this->table(['Country', 'State Name', 'Duplicates', 'IDs', 'Photos Affected'], $rows);
            $total = count($dupStates);
            if ($total >= 30) {
                $this->warn("  (showing top 30 of {$total}+)");
            }
        }

        // ── 4. Duplicate Cities (by state_id + city name) ──
        $this->newLine();
        $this->info('── Duplicate Cities (by state_id + city name) ──');

        $dupCities = DB::select("
            SELECT ci.state_id, s.state as state_name, ci.city, COUNT(*) as cnt,
                   GROUP_CONCAT(ci.id ORDER BY ci.id SEPARATOR ', ') as ids,
                   (SELECT COUNT(*) FROM photos p WHERE p.city_id IN (
                       SELECT ci2.id FROM cities ci2 WHERE ci2.state_id = ci.state_id AND ci2.city = ci.city
                   )) as total_photos
            FROM cities ci
            LEFT JOIN states s ON s.id = ci.state_id
            GROUP BY ci.state_id, ci.city
            HAVING cnt > 1
            ORDER BY total_photos DESC
            LIMIT 30
        ");

        if (empty($dupCities)) {
            $this->info('  None ✓');
        } else {
            $rows = array_map(fn($d) => [$d->state_name, $d->city, $d->cnt, $d->ids, number_format($d->total_photos)], $dupCities);
            $this->table(['State', 'City Name', 'Duplicates', 'IDs', 'Photos Affected'], $rows);
            $total = count($dupCities);
            if ($total >= 30) {
                $this->warn("  (showing top 30 of {$total}+)");
            }
        }

        // ── 5. Tier Consistency ──
        $this->newLine();
        $this->info('── Tier Consistency ──');

        $tierChecks = DB::select("
            SELECT 'photo.country ≠ state.country' as issue, COUNT(*) as cnt
            FROM photos p
            JOIN states s ON s.id = p.state_id
            WHERE p.country_id != s.country_id
            UNION ALL
            SELECT 'photo.state ≠ city.state', COUNT(*)
            FROM photos p
            JOIN cities c ON c.id = p.city_id
            WHERE p.state_id != c.state_id
            UNION ALL
            SELECT 'photo.country ≠ city.country', COUNT(*)
            FROM photos p
            JOIN cities c ON c.id = p.city_id
            WHERE p.country_id != c.country_id
            UNION ALL
            SELECT 'city.country ≠ state.country', COUNT(*)
            FROM cities c
            JOIN states s ON s.id = c.state_id
            WHERE c.country_id != s.country_id
        ");

        $hasIssues = false;
        foreach ($tierChecks as $check) {
            $status = $check->cnt == 0 ? '✓' : '✗';
            $this->info("  {$status} {$check->issue}: " . number_format($check->cnt));
            if ($check->cnt > 0) $hasIssues = true;
        }

        // ── 6. Broken References ──
        $this->newLine();
        $this->info('── Broken Foreign Key References ──');

        $brokenRefs = DB::select("
            SELECT 'photos → countries' as ref, COUNT(*) as cnt
            FROM photos p
            LEFT JOIN countries c ON c.id = p.country_id
            WHERE c.id IS NULL AND p.country_id IS NOT NULL
            UNION ALL
            SELECT 'photos → states', COUNT(*)
            FROM photos p
            LEFT JOIN states s ON s.id = p.state_id
            WHERE s.id IS NULL AND p.state_id IS NOT NULL
            UNION ALL
            SELECT 'photos → cities', COUNT(*)
            FROM photos p
            LEFT JOIN cities c ON c.id = p.city_id
            WHERE c.id IS NULL AND p.city_id IS NOT NULL
            UNION ALL
            SELECT 'cities → states', COUNT(*)
            FROM cities c
            LEFT JOIN states s ON s.id = c.state_id
            WHERE s.id IS NULL AND c.state_id IS NOT NULL
            UNION ALL
            SELECT 'cities → countries', COUNT(*)
            FROM cities c
            LEFT JOIN countries co ON co.id = c.country_id
            WHERE co.id IS NULL AND c.country_id IS NOT NULL
            UNION ALL
            SELECT 'states → countries', COUNT(*)
            FROM states s
            LEFT JOIN countries c ON c.id = s.country_id
            WHERE c.id IS NULL AND s.country_id IS NOT NULL
        ");

        foreach ($brokenRefs as $ref) {
            $status = $ref->cnt == 0 ? '✓' : '✗';
            $this->info("  {$status} {$ref->ref}: " . number_format($ref->cnt));
        }

        // ── 7. Orphans ──
        $this->newLine();
        $this->info('── Orphaned Locations ──');

        $orphans = DB::select("
            SELECT 'States (no photos, no cities)' as type, COUNT(*) as cnt
            FROM states s
            WHERE NOT EXISTS (SELECT 1 FROM photos p WHERE p.state_id = s.id)
            AND NOT EXISTS (SELECT 1 FROM cities c WHERE c.state_id = s.id)
            UNION ALL
            SELECT 'States (no photos, has cities)', COUNT(*)
            FROM states s
            WHERE NOT EXISTS (SELECT 1 FROM photos p WHERE p.state_id = s.id)
            AND EXISTS (SELECT 1 FROM cities c WHERE c.state_id = s.id)
            UNION ALL
            SELECT 'Cities (no photos)', COUNT(*)
            FROM cities c
            WHERE NOT EXISTS (SELECT 1 FROM photos p WHERE p.city_id = c.id)
        ");

        foreach ($orphans as $o) {
            $status = $o->cnt == 0 ? '✓' : '⚠';
            $this->info("  {$status} {$o->type}: " . number_format($o->cnt));
        }

        // ── 8. Unique Constraints ──
        $this->newLine();
        $this->info('── Unique Constraints ──');

        $constraints = [
            ['countries', 'uq_country_shortcode'],
            ['states', 'uq_state_country'],
            ['cities', 'uq_city_state'],
        ];

        foreach ($constraints as [$table, $indexName]) {
            $exists = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
            $status = !empty($exists) ? '✓' : '✗';
            $this->info("  {$status} {$table}.{$indexName}");
        }

        // ── 9. "not found" cities ──
        $this->newLine();
        $this->info('── "not found" Cities ──');

        $notFound = DB::selectOne("SELECT COUNT(*) as cnt FROM cities WHERE city = 'not found'");
        $status = $notFound->cnt == 0 ? '✓' : '✗';
        $this->info("  {$status} Count: {$notFound->cnt}");

        if ($notFound->cnt > 0) {
            $nfPhotos = DB::selectOne("
                SELECT COUNT(*) as cnt FROM photos p
                JOIN cities c ON c.id = p.city_id
                WHERE c.city = 'not found'
            ");
            $this->info("  Photos affected: " . number_format($nfPhotos->cnt));
        }

        // ── Summary ──
        $this->newLine();
        $this->info('═══════════════════════════════════════════════');
        $dupTotal = count($dupCountriesByName) + count($dupCountriesByCode) + count($dupStates) + count($dupCities);
        if ($dupTotal === 0 && !$hasIssues) {
            $this->info('  ✓ Location data is clean');
        } else {
            if ($dupTotal > 0) {
                $this->warn("  ✗ {$dupTotal} duplicate group(s) found");
            }
            if ($hasIssues) {
                $this->warn('  ✗ Tier consistency issues found');
            }
        }
        $this->info('═══════════════════════════════════════════════');

        return self::SUCCESS;
    }
}
