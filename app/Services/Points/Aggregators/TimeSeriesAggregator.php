<?php

namespace App\Services\Points\Aggregators;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TimeSeriesAggregator
{
    /**
     * Aggregate time series data
     */
    public function aggregate(Collection $photoIds, array $params): array
    {
        if ($photoIds->isEmpty()) {
            return [];
        }

        $groupBy = $this->determineGroupBy($params);

        $results = DB::table('photos')
            ->whereIn('id', $photoIds)
            ->selectRaw("
                {$groupBy} as bucket,
                COUNT(*) as photos,
                COALESCE(SUM(total_litter), 0) as objects
            ")
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get();

        return $results->map(function($row) {
            return (object)[
                'bucket' => $row->bucket,
                'photos' => (int)$row->photos,
                'objects' => (int)$row->objects,
            ];
        })->toArray();
    }

    /**
     * Determine appropriate time grouping based on date range
     */
    private function determineGroupBy(array $params): string
    {
        // If date range is provided, determine grouping based on range size
        if (!empty($params['from']) && !empty($params['to'])) {
            $from = Carbon::parse($params['from']);
            $to = Carbon::parse($params['to']);
            $days = $from->diffInDays($to);

            if ($days <= 7) {
                // Daily for week or less
                return 'DATE(datetime)';
            } elseif ($days <= 90) {
                // Still daily for up to 3 months
                return 'DATE(datetime)';
            } elseif ($days <= 365) {
                // Weekly for up to a year
                return "DATE_FORMAT(datetime, '%Y-%u')";
            } else {
                // Monthly for larger ranges
                return "DATE_FORMAT(datetime, '%Y-%m-01')";
            }
        }

        // Default to monthly
        return "DATE_FORMAT(datetime, '%Y-%m-01')";
    }
}
