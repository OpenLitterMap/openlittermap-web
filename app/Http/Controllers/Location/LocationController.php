<?php

declare(strict_types=1);

namespace App\Http\Controllers\Location;

use App\Enums\LocationType;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Locations API — replaces GetDataForWorldCupController + old LocationController
 *
 * Two endpoints, one controller, no service layer.
 * All stats come from the metrics table (timescale=0, user_id=0).
 * All children loaded at once (max ~262) — frontend sorts client-side.
 *
 * Routes (add to routes/api.php):
 *   Route::prefix('v1')->group(function () {
 *       Route::get('locations', [LocationController::class, 'index']);
 *       Route::get('locations/{type}/{id}', [LocationController::class, 'show'])
 *           ->where('type', 'country|state|city')
 *           ->where('id', '[0-9]+');
 *   });
 */
class LocationController extends Controller
{
    /**
     * GET /api/locations/global
     *
     * Alias for index() — used by the non-versioned route.
     */
    public function global(): JsonResponse
    {
        return $this->index();
    }

    /**
     * GET /api/v1/locations?year=2025&month=6
     *
     * Global stats + all countries with their stats.
     * Optional year/month filters use metrics timescale 4 (yearly) or 3 (monthly).
     */
    public function index(): JsonResponse
    {
        $time = $this->resolveTimeFilter();

        $stats = $this->getStats(LocationType::Global, 0, $time);
        $stats['contributors'] = Cache::remember('locations:global:contributors', 300, function () {
            return (int) DB::table('metrics')
                ->where('timescale', 0)
                ->where('location_type', LocationType::Global->value)
                ->where('location_id', 0)
                ->where('user_id', '>', 0)
                ->where('xp', '>', 0)
                ->count();
        });
        $stats['total_users'] = Cache::remember('locations:global:users_count', 300, function () {
            return (int) DB::table('users')->count();
        });

        $countries = $this->getChildrenWithStats(
            'countries', 'c', 'country', LocationType::Country, $time
        );

        $stats['countries'] = $countries->count();

        $this->enrichChildrenMeta($countries, 'country_id', $stats);

        // Recent activity from metrics table (always shown regardless of time filter)
        $activity = $this->getGlobalActivity();

        return response()->json([
            'stats' => $stats,
            'activity' => $activity,
            'locations' => $countries,
            'location_type' => 'country',
            'breadcrumbs' => [
                ['name' => 'World', 'type' => 'global', 'id' => null],
            ],
        ]);
    }

    /**
     * GET /api/v1/locations/{type}/{id}?period=this_month
     *
     * Location detail + stats + children with stats + breadcrumbs.
     */
    public function show(string $type, int $id): JsonResponse
    {
        $locationType = $this->resolveType($type);
        $time = $this->resolveTimeFilter();

        $stats = $this->getStats($locationType, $id, $time);
        $stats['contributors'] = $this->countContributors($locationType, $id);

        [$location, $children, $childrenType, $breadcrumbs] = match ($locationType) {
            LocationType::Country => $this->loadCountry($id, $time),
            LocationType::State   => $this->loadState($id, $time),
            LocationType::City    => $this->loadCity($id),
        };

        $meta = $this->getLocationMeta($locationType, $id, $stats);

        // Enrich children with meta
        if ($children && $childrenType) {
            $childColumn = match ($childrenType) {
                'state' => 'state_id',
                'city'  => 'city_id',
                default => null,
            };
            if ($childColumn) {
                $this->enrichChildrenMeta($children, $childColumn, $stats);
            }
        }

        // Add child location count (e.g. "12 states", "5 cities")
        if ($children && $childrenType) {
            $stats[$childrenType . '_count'] = count($children);
        }

        return response()->json([
            'location' => $location,
            'stats' => $stats,
            'meta' => $meta,
            'activity' => $this->getActivity($locationType, $id),
            'locations' => $children,
            'location_type' => $childrenType,
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    // ─── Time filter resolution ─────────────────────────────────────

    /**
     * Parse time filter from request.
     *
     * Supports two modes:
     *   ?period=today|yesterday|this_month|last_month|this_year|all
     *   ?year=2025&month=6   (custom)
     *
     * Returns: [timescale, year, month, bucketDate]
     */
    private function resolveTimeFilter(): array
    {
        $period = request()->query('period');

        if ($period) {
            $today = now('UTC');
            $yesterday = $today->copy()->subDay();
            $lastMonth = $today->copy()->subMonth();

            return match ($period) {
                'today'      => [1, $today->year, $today->month, $today->toDateString()],
                'yesterday'  => [1, $yesterday->year, $yesterday->month, $yesterday->toDateString()],
                'this_month' => [3, $today->year, $today->month, null],
                'last_month' => [3, $lastMonth->year, $lastMonth->month, null],
                'this_year'  => [4, $today->year, 0, null],
                default      => [0, 0, 0, null],
            };
        }

        // Custom year/month
        $year = (int) request()->query('year', 0);
        $month = (int) request()->query('month', 0);

        if ($year > 0 && ($year < 2015 || $year > (int) date('Y'))) {
            abort(422, 'Invalid year');
        }
        if ($month > 0 && ($month < 1 || $month > 12)) {
            abort(422, 'Invalid month');
        }
        if ($month > 0 && $year === 0) {
            $month = 0;
        }

        if ($year > 0 && $month > 0) {
            return [3, $year, $month, null];
        }
        if ($year > 0) {
            return [4, $year, 0, null];
        }

        return [0, 0, 0, null];
    }

    // ─── Country ────────────────────────────────────────────────────

    private function loadCountry(int $id, array $time): array
    {
        $country = DB::table('countries')
            ->where('id', $id)
            ->first(['id', 'country as name', 'shortcode']);

        abort_unless($country, 404, 'Country not found');

        $states = $this->getChildrenWithStats(
            'states', 's', 'state', LocationType::State, $time,
            fn ($q) => $q->where('s.country_id', $id)
        );

        $breadcrumbs = [
            ['name' => 'World', 'type' => 'global', 'id' => null],
            ['name' => $country->name, 'type' => 'country', 'id' => $country->id],
        ];

        return [$country, $states, 'state', $breadcrumbs];
    }

    // ─── State ──────────────────────────────────────────────────────

    private function loadState(int $id, array $time): array
    {
        $state = DB::table('states as s')
            ->join('countries as c', 'c.id', '=', 's.country_id')
            ->where('s.id', $id)
            ->first([
                's.id',
                's.state as name',
                's.country_id',
                'c.country as country_name',
                'c.shortcode as country_shortcode',
            ]);

        abort_unless($state, 404, 'State not found');

        $cities = $this->getChildrenWithStats(
            'cities', 'ci', 'city', LocationType::City, $time,
            fn ($q) => $q->where('ci.state_id', $id)
        );

        $location = (object) ['id' => $state->id, 'name' => $state->name];

        $breadcrumbs = [
            ['name' => 'World', 'type' => 'global', 'id' => null],
            ['name' => $state->country_name, 'type' => 'country', 'id' => $state->country_id],
            ['name' => $state->name, 'type' => 'state', 'id' => $state->id],
        ];

        return [$location, $cities, 'city', $breadcrumbs];
    }

    // ─── City ───────────────────────────────────────────────────────

    private function loadCity(int $id): array
    {
        $city = DB::table('cities as ci')
            ->join('states as s', 's.id', '=', 'ci.state_id')
            ->join('countries as c', 'c.id', '=', 's.country_id')
            ->where('ci.id', $id)
            ->first([
                'ci.id',
                'ci.city as name',
                'ci.state_id',
                's.state as state_name',
                's.country_id',
                'c.country as country_name',
                'c.shortcode as country_shortcode',
            ]);

        abort_unless($city, 404, 'City not found');

        $location = (object) ['id' => $city->id, 'name' => $city->name];

        $breadcrumbs = [
            ['name' => 'World', 'type' => 'global', 'id' => null],
            ['name' => $city->country_name, 'type' => 'country', 'id' => $city->country_id],
            ['name' => $city->state_name, 'type' => 'state', 'id' => $city->state_id],
            ['name' => $city->name, 'type' => 'city', 'id' => $city->id],
        ];

        return [$location, null, null, $breadcrumbs];
    }

    // ─── Shared helpers ─────────────────────────────────────────────

    /**
     * Build the children query with time-filtered metrics JOIN.
     *
     * @param string $table      Location table name (countries, states, cities)
     * @param string $alias      Table alias (c, s, ci)
     * @param string $nameCol    Name column (country, state, city)
     * @param LocationType $childType  LocationType for the metrics join
     * @param array $time        [timescale, year, month, bucketDate]
     * @param \Closure|null $scope Additional where clause
     */
    private function getChildrenWithStats(
        string $table,
        string $alias,
        string $nameCol,
        LocationType $childType,
        array $time,
        ?\Closure $scope = null
    ) {
        [$timescale, $year, $month, $bucketDate] = $time;

        $query = DB::table("{$table} as {$alias}")
            ->leftJoin('metrics as m', function ($join) use ($alias, $childType, $timescale, $year, $month, $bucketDate) {
                $join->on('m.location_id', '=', "{$alias}.id")
                    ->where('m.location_type', $childType)
                    ->where('m.timescale', $timescale)
                    ->where('m.user_id', 0);

                if ($bucketDate) {
                    $join->where('m.bucket_date', $bucketDate);
                } else {
                    if ($year > 0) {
                        $join->where('m.year', $year);
                    }
                    if ($month > 0) {
                        $join->where('m.month', $month);
                    }
                }
            })
            ->where(function ($q) {
                $q->where('m.uploads', '>', 0)
                    ->orWhere('m.tags', '>', 0);
            })
            ->orderByDesc('m.tags');

        if ($scope) {
            $scope($query);
        }

        $columns = [
            "{$alias}.id",
            "{$alias}.{$nameCol} as name",
            DB::raw('COALESCE(m.uploads, 0) as total_images'),
            DB::raw('COALESCE(m.tags, 0) as total_tags'),
            DB::raw('COALESCE(m.xp, 0) as xp'),
            "{$alias}.created_at",
            "{$alias}.updated_at",
        ];

        // Include shortcode for countries
        if ($table === 'countries') {
            $columns[] = "{$alias}.shortcode";
        }

        return $query->get($columns);
    }

    private function getStats(LocationType $type, int $id, array $time): array
    {
        [$timescale, $year, $month, $bucketDate] = $time;

        $query = DB::table('metrics')
            ->where('timescale', $timescale)
            ->where('location_type', $type)
            ->where('location_id', $id)
            ->where('user_id', 0);

        if ($bucketDate) {
            $query->where('bucket_date', $bucketDate);
        } else {
            if ($year > 0) {
                $query->where('year', $year);
            }
            if ($month > 0) {
                $query->where('month', $month);
            }
        }

        $row = $query->first(['uploads', 'litter', 'xp', 'tags', 'brands']);

        return [
            'photos' => (int) ($row->uploads ?? 0),
            'tags'   => (int) ($row->tags ?? 0),
            'xp'     => (int) ($row->xp ?? 0),
            'litter' => (int) ($row->litter ?? 0),
            'brands' => (int) ($row->brands ?? 0),
        ];
    }

    private function countContributors(LocationType $type, int $id): int
    {
        return (int) Cache::remember("location:{$type->value}:{$id}:contributors", 300, fn () =>
            DB::table('metrics')
                ->where('timescale', 0)
                ->where('location_type', $type->value)
                ->where('location_id', $id)
                ->where('user_id', '>', 0)
                ->where('xp', '>', 0)
                ->count()
        );
    }

    /**
     * Get location meta: % of global, averages.
     * First/last uploader queries removed — they caused full table scans on photos.
     */
    private function getLocationMeta(LocationType $type, int $id, array $stats): array
    {
        if (!in_array($type, [LocationType::Country, LocationType::State, LocationType::City])) {
            return [];
        }

        // Global totals for percentage (all-time)
        $global = Cache::remember('location:global:totals', 300, fn () =>
            DB::table('metrics')
                ->where('timescale', 0)
                ->where('location_type', LocationType::Global->value)
                ->where('location_id', 0)
                ->where('user_id', 0)
                ->first(['uploads', 'tags'])
        );

        $globalPhotos = (int) ($global->uploads ?? 0);
        $globalTags = (int) ($global->tags ?? 0);

        return [
            'pct_photos' => $globalPhotos > 0 ? round(($stats['photos'] / $globalPhotos) * 100, 2) : 0,
            'pct_tags' => $globalTags > 0 ? round(($stats['tags'] / $globalTags) * 100, 2) : 0,
            'avg_photos_per_person' => $stats['contributors'] > 0
                ? round($stats['photos'] / $stats['contributors'], 1) : 0,
            'avg_tags_per_person' => $stats['contributors'] > 0
                ? round($stats['tags'] / $stats['contributors'], 1) : 0,
        ];
    }

    private function resolveType(string $type): LocationType
    {
        return match ($type) {
            'country' => LocationType::Country,
            'state'   => LocationType::State,
            'city'    => LocationType::City,
            default   => abort(404, "Invalid location type: {$type}"),
        };
    }

    /**
     * Batch-enrich a children collection with contributors, %, and averages.
     * Single query against the metrics table (indexed).
     */
    private function enrichChildrenMeta($children, string $photoColumn, array $parentStats): void
    {
        if ($children->isEmpty()) {
            return;
        }

        $ids = $children->pluck('id')->all();

        // Resolve location type from the photo column
        $locationType = match ($photoColumn) {
            'country_id' => LocationType::Country,
            'state_id'   => LocationType::State,
            'city_id'    => LocationType::City,
        };

        // Count contributors per location from metrics (uses idx_leaderboard)
        $contributors = DB::table('metrics')
            ->select('location_id', DB::raw('COUNT(*) as contributors'))
            ->where('timescale', 0)
            ->where('location_type', $locationType->value)
            ->whereIn('location_id', $ids)
            ->where('user_id', '>', 0)
            ->where('xp', '>', 0)
            ->groupBy('location_id')
            ->pluck('contributors', 'location_id');

        $parentPhotos = $parentStats['photos'] ?: 1;
        $parentTags = $parentStats['tags'] ?: 1;

        foreach ($children as $child) {
            $contribs = (int) ($contributors[$child->id] ?? 0);

            $child->total_members = $contribs;
            $child->pct_tags = round(($child->total_tags / $parentTags) * 100, 1);
            $child->pct_photos = round(($child->total_images / $parentPhotos) * 100, 1);
            $child->avg_tags_per_person = $contribs > 0 ? round($child->total_tags / $contribs, 1) : 0;
            $child->avg_photos_per_person = $contribs > 0 ? round($child->total_images / $contribs, 1) : 0;
        }
    }

    /**
     * Get recent activity for the global page.
     * Returns today + this month from the metrics table.
     */
    private function getGlobalActivity(): array
    {
        return $this->getActivity(LocationType::Global, 0);
    }

    /**
     * Get recent activity for any location.
     * Two queries: today (daily bucket) + this month (monthly bucket).
     */
    private function getActivity(LocationType $type, int $id): array
    {
        $today = now('UTC');

        // Today: timescale=1, bucket_date=today
        $todayRow = DB::table('metrics')
            ->where('timescale', 1)
            ->where('location_type', $type)
            ->where('location_id', $id)
            ->where('user_id', 0)
            ->where('bucket_date', $today->toDateString())
            ->first(['uploads', 'tags', 'xp']);

        // This month: timescale=3, year+month
        $monthRow = DB::table('metrics')
            ->where('timescale', 3)
            ->where('location_type', $type)
            ->where('location_id', $id)
            ->where('user_id', 0)
            ->where('year', $today->year)
            ->where('month', $today->month)
            ->first(['uploads', 'tags', 'xp']);

        return [
            'today' => [
                'photos' => (int) ($todayRow->uploads ?? 0),
                'tags'   => (int) ($todayRow->tags ?? 0),
                'xp'     => (int) ($todayRow->xp ?? 0),
            ],
            'this_month' => [
                'photos' => (int) ($monthRow->uploads ?? 0),
                'tags'   => (int) ($monthRow->tags ?? 0),
                'xp'     => (int) ($monthRow->xp ?? 0),
            ],
        ];
    }
}
