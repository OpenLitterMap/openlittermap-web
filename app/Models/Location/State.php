<?php

namespace App\Models\Location;

class State extends Location
{
    protected $fillable = [
        'id',
        'state',
        'country_id',
        'created_at',
        'updated_at',
        'manual_verify',
        'littercoin_paid',
        'created_by',
        'user_id_last_uploaded'
    ];

    /**
     * Extra columns appended to JSON
     */
    protected $appends = [
        'total_litter_redis',
        'total_photos_redis',
        'total_contributors_redis',
        'litter_data',
        'brands_data',
        'objects_data',
        'materials_data',
        'recent_activity',
        'total_xp',
        'ppm',
        'updatedAtDiffForHumans',
        'total_ppm'
    ];

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function cities()
    {
        return $this->hasMany(City::class);
    }
}
