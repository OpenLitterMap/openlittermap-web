<?php

namespace App\Models\Location;

class Country extends Location
{
    protected $fillable = [
        'country',
        'shortcode',
        'created_by',
    ];

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
        'total_ppm',
    ];

    public function getRouteKeyName(): string
    {
        return 'shortcode';
    }

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
