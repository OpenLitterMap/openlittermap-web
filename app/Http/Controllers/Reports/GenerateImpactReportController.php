<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use Carbon\Carbon;
use App\Enums\LocationType;
use App\Models\Users\User;
use Illuminate\View\View;
use Illuminate\Support\Number;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\{Cache, DB};

class GenerateImpactReportController extends Controller
{
    /**
     * Generate a weekly or monthly impact report.
     *
     * v5: Uses the metrics table for aggregates and photo_tags for breakdowns.
     * No cursor, no N+1, no Redis day-loop, no litter.json.
     */
    public function __invoke(string $period = 'weekly', $year = null, $monthOrWeek = null): View
    {
        $period = in_array($period, ['weekly', 'monthly']) ? $period : 'weekly';

        [$start, $end] = $this->resolveDateRange($period, $year, $monthOrWeek);

        if (Carbon::parse($start)->isFuture()) {
            return view('pages.not-found');
        }

        $cacheKey = "impact_report:{$period}:{$start}_{$end}";
        $cacheTtl = Carbon::parse($end)->endOfDay();

        $report = Cache::remember(
            $cacheKey,
            $cacheTtl,
            fn () => $this->buildReport($period, $start, $end)
        );

        return view('reports.impact', $report);
    }

    /* ------------------------------------------------------------------
     *  Report builder
     * ------------------------------------------------------------------ */

    private function buildReport(string $period, string $start, string $end): array
    {
        $dateFormat = $period === 'monthly' ? 'F Y' : 'D jS M Y';

        // One row each — metrics table does the heavy lifting
        $periodRow = $this->getMetricsRow($period, $start);
        $allTimeRow = $this->getMetricsRow('all_time');

        return [
            'period'      => $period,
            'startDate'   => Carbon::parse($start)->format($dateFormat),
            'endDate'     => Carbon::parse($end)->format($dateFormat),

            'newUsers'    => User::whereBetween('created_at', [$start, $end])->count(),
            'totalUsers'  => User::count(),

            'newPhotos'   => (int) ($periodRow->uploads ?? 0),
            'totalPhotos' => (int) ($allTimeRow->uploads ?? 0),

            'newTags'     => (int) ($periodRow->tags ?? 0),
            'totalTags'   => (int) ($allTimeRow->tags ?? 0),

            'topUsers'    => $this->getTopUsers($start, $end),
            'topTags'     => $this->getTopObjects($start, $end),
            'topBrands'   => $this->getTopBrands($start, $end),

            'medals'      => [
                ['src' => 'https://openlittermap.com/assets/icons/gold-medal-2.png',   'alt' => 'Gold Medal'],
                ['src' => 'https://openlittermap.com/assets/icons/silver-medal-2.png',  'alt' => 'Silver Medal'],
                ['src' => 'https://openlittermap.com/assets/icons/bronze-medal-2.png',  'alt' => 'Bronze Medal'],
            ],
        ];
    }

    /* ------------------------------------------------------------------
     *  Metrics table lookups (single row each)
     * ------------------------------------------------------------------ */

    private function getMetricsRow(string $period, ?string $start = null): ?object
    {
        $query = DB::table('metrics')
            ->where('location_type', LocationType::Global)
            ->where('location_id', 0)
            ->where('user_id', 0);

        if ($period === 'all_time') {
            $query->where('timescale', 0);
        } elseif ($period === 'weekly') {
            $date = Carbon::parse($start);
            $query->where('timescale', 2)
                ->where('year', (int) $date->format('o'))
                ->where('week', (int) $date->format('W'));
        } else {
            $date = Carbon::parse($start);
            $query->where('timescale', 3)
                ->where('year', $date->year)
                ->where('month', $date->month);
        }

        return $query->first(['uploads', 'tags', 'brands', 'litter', 'xp']);
    }

    /* ------------------------------------------------------------------
     *  Top 10 users by XP in the period
     * ------------------------------------------------------------------ */

    private function getTopUsers(string $start, string $end): array
    {
        $rows = DB::table('photos')
            ->select('user_id', DB::raw('SUM(xp) as total_xp'))
            ->whereBetween('created_at', [$start, $end])
            ->where('xp', '>', 0)
            ->groupBy('user_id')
            ->orderByDesc('total_xp')
            ->limit(10)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $users = User::query()
            ->with('teams:id,name')
            ->whereIn('id', $rows->pluck('user_id'))
            ->get()
            ->keyBy('id');

        return $rows
            ->map(function ($row, int $index) use ($users) {
                $user = $users->get($row->user_id);
                if (! $user) return null;

                return [
                    'name'        => $user->show_name ? $user->name : '',
                    'username'    => $user->show_username ? ('@' . $user->username) : '',
                    'xp'          => number_format((int) $row->total_xp),
                    'global_flag' => $user->global_flag,
                    'social'      => $user->social_links ?: null,
                    'team'        => $user->teams->first()?->name ?? '',
                    'rank'        => $index + 1,
                    'ordinal'     => Number::ordinal($index + 1),
                ];
            })
            ->filter()
            ->values()
            ->toArray();
    }

    /* ------------------------------------------------------------------
     *  Top 10 litter objects (single query via photo_tags)
     * ------------------------------------------------------------------ */

    private function getTopObjects(string $start, string $end): array
    {
        return DB::table('photo_tags as pt')
            ->join('photos as p', 'p.id', '=', 'pt.photo_id')
            ->join('litter_objects as lo', 'lo.id', '=', 'pt.litter_object_id')
            ->whereBetween('p.created_at', [$start, $end])
            ->whereNotNull('pt.litter_object_id')
            ->select('lo.key', DB::raw('SUM(pt.quantity) as total'))
            ->groupBy('lo.key')
            ->orderByDesc('total')
            ->limit(10)
            ->pluck('total', 'key')
            ->map(fn ($v) => (int) $v)
            ->toArray();
    }

    /* ------------------------------------------------------------------
     *  Top 10 brands (single query via photo_tag_extra_tags)
     * ------------------------------------------------------------------ */

    private function getTopBrands(string $start, string $end): array
    {
        return DB::table('photo_tag_extra_tags as ptet')
            ->join('photo_tags as pt', 'pt.id', '=', 'ptet.photo_tag_id')
            ->join('photos as p', 'p.id', '=', 'pt.photo_id')
            ->join('brandslist as bl', 'bl.id', '=', 'ptet.tag_type_id')
            ->where('ptet.tag_type', 'brand')
            ->whereBetween('p.created_at', [$start, $end])
            ->select('bl.key', DB::raw('SUM(ptet.quantity) as total'))
            ->groupBy('bl.key')
            ->orderByDesc('total')
            ->limit(10)
            ->pluck('total', 'key')
            ->map(fn ($v) => (int) $v)
            ->toArray();
    }

    /* ------------------------------------------------------------------
     *  Date range resolution
     * ------------------------------------------------------------------ */

    private function resolveDateRange(string $period, $year, $monthOrWeek): array
    {
        $hasParams = $year !== null && $monthOrWeek !== null;

        if ($period === 'weekly') {
            $start = $hasParams
                ? Carbon::now()->setISODate((int) $year, (int) $monthOrWeek)->startOfWeek()
                : now()->subWeek()->startOfWeek();

            return [$start->toDateTimeString(), $start->copy()->endOfWeek()->toDateTimeString()];
        }

        $start = $hasParams
            ? Carbon::createFromDate((int) $year, (int) $monthOrWeek, 1)->startOfMonth()
            : now()->startOfMonth();

        return [$start->toDateTimeString(), $start->copy()->endOfMonth()->toDateTimeString()];
    }
}
