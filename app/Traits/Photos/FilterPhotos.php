<?php

namespace App\Traits\Photos;

use App\Models\Photo;
use GeoHash;

trait FilterPhotos
{
    /**
     * Filter the users photos
     *
     * - Used by
     *  - bulk tagging
     *  - view the users photos
     *
     * @param $filters_json
     *     id,
     *     min date, max date
     *     created_at, datetime
     *     status: 0,2. verification status
     * @param $selectAll || null
     * @param $ids || null
     *
     * @return $query
     */
    public function filterPhotos (
        $filters_json,
        $selectAll = null,
        $ids = null
    )
    {
        $filters = json_decode($filters_json);

        $query = Photo::query();
        $query->where('user_id', auth()->user()->id);

        // Verification status: 0, 2
        $query->where('verified', $filters->status);

        if (!is_null($selectAll))
        {
            // If selectAll is false, and the user is passing IDs,
            if ($selectAll === false && !is_null($ids) && sizeof($ids) > 0)
            {
                // we only want to select these IDs
                $query->whereIn('id', $ids);

                return $query;
            }
        }

        // Filter by photo.id
        if (strlen($filters->id) > 0)
        {
            $id = $filters->id;

            $query->where(function ($q) use ($id) {
                return $q->where([
                    'user_id' => auth()->user()->id,
                    ['id', 'like', '%'.$id.'%']
                ]);
            });
        }

        if ($filters->dateRange->start && $filters->dateRange->end)
        {
            $start = $filters->dateRange->start;
            $end = $filters->dateRange->end;

            $query->where('created_at', '>=', $start . ' 00:00:00');
            $query->where('created_at', '<=', $end . ' 23:59:59');
        }

        if (!is_null($selectAll) && $selectAll)
        {
            // Do not include these ids
            $query->whereNotIn('id', $ids);
        }

        return $query;
    }
}
