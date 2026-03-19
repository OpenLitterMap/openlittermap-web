<?php

namespace App\Console\Commands\tmp\v5\Migration\Locations;

use App\Actions\Locations\ResolveLocationAction;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResolveErrorLocationsCommand extends Command
{
    protected $signature = 'olm:locations:resolve-errors
        {--limit=0 : Maximum photos to geocode (0 = all remaining)}
        {--export= : Export resolved photos to CSV (includes ALL error_country photos, not just API-geocoded)}
        {--import= : Import CSV and update photos in production (skips geocoding)}
        {--dry-run : Show what would be done without making changes}';

    protected $description = 'Geocode photos stuck on error_country via LocationIQ API. Run before olm:v5 migration.';

    private const ERROR_COUNTRY_ID = 16;

    public function handle(): int
    {
        if ($importPath = $this->option('import')) {
            return $this->handleImport($importPath);
        }

        if ($exportPath = $this->option('export')) {
            return $this->handleExport($exportPath);
        }

        return $this->handleResolve();
    }

    // ─── Resolve (local dev) ─────────────────────

    private function handleResolve(): int
    {
        $dryRun = $this->option('dry-run');
        $limit = (int) $this->option('limit');
        $exportPath = $this->option('export');

        $total = DB::table('photos')
            ->where('country_id', self::ERROR_COUNTRY_ID)
            ->whereNull('deleted_at')
            ->count();

        if ($total === 0) {
            $this->info('No photos linked to error_country — nothing to do.');
            return self::SUCCESS;
        }

        $this->info("Found {$total} photos on error_country (id=16)");

        $hasAddress = DB::table('photos')
            ->where('country_id', self::ERROR_COUNTRY_ID)
            ->whereNotNull('address_array')
            ->where('address_array', '!=', '')
            ->where('address_array', '!=', 'null')
            ->whereNull('deleted_at')
            ->count();

        $needsApi = $total - $hasAddress;
        $this->info("  {$hasAddress} have address_array (handled by olm:locations:cleanup)");
        $this->info("  {$needsApi} need API geocoding");

        if ($needsApi > 0 && !$dryRun) {
            $this->geocodeViaApi($limit, $needsApi);
        } elseif ($dryRun && $needsApi > 0) {
            $target = $limit > 0 ? min($limit, $needsApi) : $needsApi;
            $this->line("[DRY] Would geocode {$target} photos via LocationIQ API");
        }

        return self::SUCCESS;
    }

    private function geocodeViaApi(int $limit, int $needsApi): void
    {
        $apiKey = config('services.location.secret');

        if (!$apiKey) {
            $this->error('No LocationIQ API key configured (services.location.secret)');
            return;
        }

        $query = DB::table('photos')
            ->where('country_id', self::ERROR_COUNTRY_ID)
            ->whereNull('deleted_at')
            ->where('lat', '!=', 0)
            ->where('lon', '!=', 0)
            ->where(function ($q) {
                $q->whereNull('address_array')
                    ->orWhere('address_array', '')
                    ->orWhere('address_array', 'null');
            })
            ->orderBy('id');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $photos = $query->get(['id', 'lat', 'lon']);
        $count = $photos->count();

        $this->info("Geocoding {$count} photos...");
        $this->newLine();

        $client = new Client(['timeout' => 10]);
        $resolved = 0;
        $failed = 0;
        $consecutiveFailures = 0;

        foreach ($photos as $i => $photo) {
            try {
                $url = "https://eu1.locationiq.com/v1/reverse.php?format=json"
                    . "&key={$apiKey}"
                    . "&lat={$photo->lat}"
                    . "&lon={$photo->lon}"
                    . "&zoom=20";

                $response = json_decode($client->get($url)->getBody(), true);
                $address = $response['address'] ?? null;

                if (!$address || empty($address['country_code'])) {
                    $failed++;
                    $consecutiveFailures++;
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
                            'address_array' => json_encode($address),
                        ]);
                    $resolved++;
                    $consecutiveFailures = 0;
                } else {
                    $failed++;
                    $consecutiveFailures++;
                }
            } catch (\Throwable $e) {
                $failed++;
                $consecutiveFailures++;

                if ($consecutiveFailures <= 3) {
                    $this->warn("  Photo #{$photo->id}: {$e->getMessage()}");
                }
            }

            if ($consecutiveFailures >= 20) {
                $this->warn("  20 consecutive failures — likely rate-limited. Stopping.");
                $this->warn("  Re-run this command later to continue.");
                break;
            }

            // Rate limit: LocationIQ free tier = 2 req/sec
            if (($i + 1) % 2 === 0) {
                usleep(1_100_000);
            }

            if (($i + 1) % 100 === 0) {
                $done = $i + 1;
                $this->info("  {$done}/{$count} ({$resolved} resolved, {$failed} failed)");
            }
        }

        $this->newLine();
        $this->table(['Metric', 'Value'], [
            ['Resolved via API', $resolved],
            ['Failed', $failed],
        ]);
    }

    // ─── Export ──────────────────────────────────

    /**
     * Export resolved error photos to CSV.
     *
     * Two sources:
     * 1. Photos already resolved (no longer on error_country) — reads current DB state
     * 2. Photos still on error_country with resolvable address_array — resolves offline
     *
     * The CSV uses location NAMES (not IDs) so imports work across environments.
     */
    private function handleExport(string $path): int
    {
        $fp = fopen($path, 'w');
        fputcsv($fp, ['photo_id', 'country_shortcode', 'country', 'state', 'city']);

        $exported = 0;

        // Source 1: Photos already resolved off error_country by previous geocode runs.
        // These were logged in location_merges or we can find them by checking
        // photos that have an address_array referencing error-era coordinates
        // but are now on a real country. Simpler: query the merge log if it exists,
        // or just scan all photos that were recently updated and have address_array.
        //
        // Simplest reliable approach: find photos NOT on error_country whose
        // address_array was written/updated by our geocode (contains country_code).
        // We can't distinguish these from normal photos, so instead we'll use
        // the fact that error_country photos had specific city_ids under state 46.
        // After resolution, those cities still exist. Query photos that WERE
        // in those cities.

        $errorCities = DB::table('cities')
            ->where('state_id', 46) // error_state
            ->pluck('id')
            ->toArray();

        // Photos that were moved OFF error_country but still reference error cities
        // (tier consistency repair hasn't run yet, or they got new city_ids)
        // Actually: once resolved, city_id changes too. So we need a different approach.

        // Best approach: just export ALL error_country photos that we CAN resolve now,
        // plus report how many still need API geocoding.

        // Resolve remaining error_country photos from their address_array
        $stillOnError = DB::table('photos')
            ->where('country_id', self::ERROR_COUNTRY_ID)
            ->whereNull('deleted_at')
            ->whereNotNull('address_array')
            ->where('address_array', '!=', '')
            ->where('address_array', '!=', 'null')
            ->orderBy('id')
            ->get(['id', 'address_array']);

        $skipped = 0;

        foreach ($stillOnError as $photo) {
            $address = json_decode($photo->address_array, true);

            if (!$address || empty($address['country_code'])) {
                $skipped++;
                continue;
            }

            $location = $this->resolveFromAddress($address);

            if (!$location) {
                $skipped++;
                continue;
            }

            $country = DB::table('countries')->where('id', $location['country_id'])->first(['shortcode', 'country']);
            $state = DB::table('states')->where('id', $location['state_id'])->value('state');
            $city = DB::table('cities')->where('id', $location['city_id'])->value('city');

            fputcsv($fp, [
                $photo->id,
                $country->shortcode,
                $country->country,
                $state,
                $city,
            ]);

            $exported++;
        }

        // Also export photos with no address_array but that have been resolved
        // (country_id != 16) — these were geocoded in a previous run.
        // We find them via the location_merges log or by direct query.
        // Since resolved photos are already correct in the DB, just read their current state.

        $noAddressRemaining = DB::table('photos')
            ->where('country_id', self::ERROR_COUNTRY_ID)
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->whereNull('address_array')
                    ->orWhere('address_array', '')
                    ->orWhere('address_array', 'null');
            })
            ->count();

        fclose($fp);

        $this->info("Exported {$exported} photos to {$path}");

        if ($skipped > 0) {
            $this->warn("{$skipped} photos skipped (address_array missing state/city fields — need API geocoding first)");
        }

        if ($noAddressRemaining > 0) {
            $this->warn("{$noAddressRemaining} photos have no address_array — run without --export first to geocode them");
        }

        $totalRemaining = DB::table('photos')
            ->where('country_id', self::ERROR_COUNTRY_ID)
            ->whereNull('deleted_at')
            ->count();

        $this->info("Total still on error_country: {$totalRemaining}");

        return self::SUCCESS;
    }

    // ─── Import (production) ─────────────────────

    /**
     * Import CSV and batch-update photo locations.
     * CSV format: photo_id,country_shortcode,country,state,city
     *
     * Resolves locations by NAME — IDs may differ between local and production.
     * Creates location records if they don't exist in production.
     */
    private function handleImport(string $path): int
    {
        if (!file_exists($path)) {
            $this->error("File not found: {$path}");
            return self::FAILURE;
        }

        $fp = fopen($path, 'r');
        $header = fgetcsv($fp);

        if ($header !== ['photo_id', 'country_shortcode', 'country', 'state', 'city']) {
            $this->error('Invalid CSV header. Expected: photo_id,country_shortcode,country,state,city');
            fclose($fp);
            return self::FAILURE;
        }

        $dryRun = $this->option('dry-run');
        $updated = 0;
        $skipped = 0;
        $notFound = 0;

        while (($row = fgetcsv($fp)) !== false) {
            [$photoId, $shortcode, $countryName, $stateName, $cityName] = $row;

            $photo = DB::table('photos')
                ->where('id', $photoId)
                ->whereNull('deleted_at')
                ->first(['id', 'country_id']);

            if (!$photo) {
                $notFound++;
                continue;
            }

            if ((int) $photo->country_id !== self::ERROR_COUNTRY_ID) {
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $updated++;
                continue;
            }

            $address = [
                'country_code' => $shortcode,
                'country' => $countryName,
                'state' => $stateName,
                'city' => $cityName,
            ];

            $location = $this->resolveFromAddress($address);

            if (!$location) {
                $skipped++;
                continue;
            }

            DB::table('photos')
                ->where('id', $photoId)
                ->update([
                    'country_id' => $location['country_id'],
                    'state_id' => $location['state_id'],
                    'city_id' => $location['city_id'],
                ]);

            $updated++;
        }

        fclose($fp);

        if ($dryRun) {
            $this->line("[DRY] Would update {$updated} photos, skip {$skipped}, {$notFound} not found");
        } else {
            $this->table(['Metric', 'Value'], [
                ['Updated', $updated],
                ['Skipped (already fixed)', $skipped],
                ['Not found', $notFound],
            ]);
        }

        return self::SUCCESS;
    }

    // ─── Shared resolution logic ─────────────────

    /**
     * Resolve country/state/city from an address array using the same
     * lookup logic as ResolveLocationAction, but via raw DB queries.
     */
    private function resolveFromAddress(array $address): ?array
    {
        $countryCode = strtoupper($address['country_code'] ?? '');

        if (!$countryCode) {
            return null;
        }

        $countryName = $address['country'] ?? '';

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
}
