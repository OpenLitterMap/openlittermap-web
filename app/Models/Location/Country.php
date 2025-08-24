<?php

namespace App\Models\Location;

class Country extends Location
{
    protected $fillable = [
        'id',
        'country',
        'shortcode',
        'created_at',
        'updated_at',
        'manual_verify',
        'countrynameb',
        'littercoin_paid',
        'created_by',
        'user_id_last_uploaded'
    ];

    public function getRouteKeyName()
    {
        return 'country';
    }

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

    /**
     * Country-specific relationships
     */
    public function states()
    {
        return $this->hasMany(State::class);
    }

    public function cities()
    {
        return $this->hasMany(City::class);
    }

    public function users()
    {
        return $this->hasMany('App\Models\Users\User');
    }
}
