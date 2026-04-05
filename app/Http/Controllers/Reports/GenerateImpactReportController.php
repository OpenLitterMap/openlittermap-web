<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use Carbon\Carbon;
use App\Enums\LocationType;
use App\Enums\Timescale;
use App\Models\Users\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Number;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\{Cache, DB};

class GenerateImpactReportController extends Controller
{
    /**
     * Generate a weekly or monthly impact report.
     *
     * v5: Uses the metrics table for aggregates and photo_tags for breakdowns.
     */
    public function __invoke(string $period = 'weekly', $year = null, $monthOrWeek = null): View
    {
        $period = in_array($period, ['weekly', 'monthly', 'annual'], true) ? $period : 'weekly';

        [$start, $endExclusive, $label] = $this->resolveDateRange($period, $year, $monthOrWeek);

        if ($start->isFuture()) {
            return view('pages.not-found');
        }

        $cacheKey = "impact_report:{$period}:{$label}";
        $cacheTtl = $endExclusive->isPast()
            ? now()->addDays(30)
            : $endExclusive->copy()->endOfDay();

        $report = Cache::remember(
            $cacheKey,
            $cacheTtl,
            fn () => $this->buildReport($period, $start, $endExclusive)
        );

        return view('reports.impact', $report);
    }

    /* ------------------------------------------------------------------
     *  Report builder
     * ------------------------------------------------------------------ */

    private function buildReport(string $period, Carbon $start, Carbon $endExclusive): array
    {
        $dateFormat = match ($period) {
            'annual'  => 'Y',
            'monthly' => 'F Y',
            default   => 'D jS M Y',
        };
        $endInclusive = $endExclusive->copy()->subSecond();

        $periodRow  = $this->getMetricsRow($period, $start);
        $allTimeRow = $this->getMetricsRow('all_time');

        return [
            'period'      => $period,
            'startDate'   => $start->format($dateFormat),
            'endDate'     => $endInclusive->format($dateFormat),

            'newUsers'    => User::where('created_at', '>=', $start)
                ->where('created_at', '<', $endExclusive)
                ->count(),
            'totalUsers'  => User::count(),

            'newPhotos'   => (int) ($periodRow->uploads ?? 0),
            'totalPhotos' => (int) ($allTimeRow->uploads ?? 0),

            'newTags'     => (int) ($periodRow->tags ?? 0),
            'totalTags'   => (int) ($allTimeRow->tags ?? 0),

            'topUsers'    => $this->getTopUsers($start, $endExclusive),
            'topTags'     => $this->getTopObjects($start, $endExclusive),
            'topBrands'   => $this->getTopBrands($start, $endExclusive),

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

    private function getMetricsRow(string $period, ?Carbon $start = null): ?object
    {
        $query = DB::table('metrics')
            ->where('location_type', LocationType::Global->value)
            ->where('location_id', 0)
            ->where('user_id', 0);

        if ($period === 'all_time') {
            $query->where('timescale', Timescale::AllTime->value);
        } elseif ($period === 'weekly') {
            $query->where('timescale', Timescale::Weekly->value)
                ->where('year', (int) $start->format('o'))
                ->where('week', (int) $start->format('W'));
        } elseif ($period === 'monthly') {
            $query->where('timescale', Timescale::Monthly->value)
                ->where('year', $start->year)
                ->where('month', $start->month);
        } elseif ($period === 'annual') {
            $query->where('timescale', Timescale::Yearly->value)
                ->where('year', $start->year);
        }

        return $query->first(['uploads', 'tags', 'brands', 'litter', 'xp']);
    }

    /* ------------------------------------------------------------------
     *  Top 10 users by XP in the period
     * ------------------------------------------------------------------ */

    private function getTopUsers(Carbon $start, Carbon $endExclusive): array
    {
        $rows = DB::table('photos')
            ->select('user_id', DB::raw('SUM(xp) as total_xp'), DB::raw('COUNT(*) as uploads'), DB::raw('SUM(total_tags) as tags'))
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $endExclusive)
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
                    'uploads'     => number_format((int) $row->uploads),
                    'tags'        => number_format((int) $row->tags),
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

    private function getTopObjects(Carbon $start, Carbon $endExclusive): array
    {
        return DB::table('photo_tags as pt')
            ->join('photos as p', 'p.id', '=', 'pt.photo_id')
            ->join('litter_objects as lo', 'lo.id', '=', 'pt.litter_object_id')
            ->where('p.created_at', '>=', $start)
            ->where('p.created_at', '<', $endExclusive)
            ->whereNotNull('pt.litter_object_id')
            ->select('lo.key', DB::raw('SUM(pt.quantity) as total'))
            ->groupBy('lo.key')
            ->orderByDesc('total')
            ->limit(10)
            ->pluck('total', 'key')
            ->mapWithKeys(fn ($total, $key) => [self::formatKey($key) => (int) $total])
            ->toArray();
    }

    /* ------------------------------------------------------------------
     *  Top 10 brands (via photo_tag_extra_tags)
     * ------------------------------------------------------------------ */

    private function getTopBrands(Carbon $start, Carbon $endExclusive): array
    {
        return DB::table('photo_tag_extra_tags as ptet')
            ->join('photo_tags as pt', 'pt.id', '=', 'ptet.photo_tag_id')
            ->join('photos as p', 'p.id', '=', 'pt.photo_id')
            ->join('brandslist as bl', 'bl.id', '=', 'ptet.tag_type_id')
            ->where('ptet.tag_type', 'brand')
            ->where('p.created_at', '>=', $start)
            ->where('p.created_at', '<', $endExclusive)
            ->select('bl.key', DB::raw('SUM(ptet.quantity) as total'))
            ->groupBy('bl.key')
            ->orderByDesc('total')
            ->limit(10)
            ->pluck('total', 'key')
            ->mapWithKeys(fn ($total, $key) => [self::formatKey($key) => (int) $total])
            ->toArray();
    }

    /**
     * beer_bottle → Beer Bottle, coca-cola → Coca Cola
     */
    private static function formatKey(string $key): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $key));
    }

    /* ------------------------------------------------------------------
     *  Date range resolution — returns [Carbon $start, Carbon $endExclusive, string $label]
     * ------------------------------------------------------------------ */

    private function resolveDateRange(string $period, $year, $monthOrWeek): array
    {
        if ($period === 'annual') {
            $yearInt = $year !== null ? (int) $year : now()->subYear()->year;
            $start = Carbon::createFromDate($yearInt, 1, 1)->startOfDay();
            $endExclusive = $start->copy()->addYear();

            return [$start, $endExclusive, (string) $yearInt];
        }

        $hasParams = $year !== null && $monthOrWeek !== null;

        if ($period === 'weekly') {
            $start = $hasParams
                ? Carbon::now()->setISODate((int) $year, (int) $monthOrWeek)->startOfWeek()
                : now()->subWeek()->startOfWeek();

            $endExclusive = $start->copy()->addWeek();
            $label = sprintf('%d-W%02d', (int) $start->format('o'), (int) $start->format('W'));

            return [$start, $endExclusive, $label];
        }

        $start = $hasParams
            ? Carbon::createFromDate((int) $year, (int) $monthOrWeek, 1)->startOfMonth()
            : now()->startOfMonth();

        $endExclusive = $start->copy()->addMonth();
        $label = sprintf('%d-%02d', $start->year, $start->month);

        return [$start, $endExclusive, $label];
    }
}
