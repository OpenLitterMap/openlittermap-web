<?php

namespace App\Traits\Photos;

use App\Models\Photo;
use GeoHash;

trait FilterPhotos
{
    /**
     * Filter the users photos
     *
     * @param string $filters_json
     *     id,
     *     min date, max date
     *     created_at, datetime
     *     verified
     *
     * @return \Illuminate\Database\Eloquent\Builder $query
     */
    public function filterPhotos (string $filters_json)
    {
        $query = Photo::query();

        $filters = json_decode($filters_json);

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

        if ($filters->verified !== null)
        {
            $query->where('verified', $filters->verified);
        }

        return $query;
    }
}
