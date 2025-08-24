<?php

namespace App\Models\Location;

class City extends Location
{
    protected $fillable = [
        'id',
        'city',
        'country_id',
        'state_id',
        'created_at',
        'updated_at',
        'total_smoking',
        'total_cigaretteButts',
        'total_food',
        'total_softdrinks',
        'total_plasticBottles',
        'total_alcohol',
        'total_coffee',
        'total_drugs',
        'total_dumping',
        'total_industrial',
        'total_needles',
        'total_sanitary',
        'total_other',
        'total_coastal',
        'total_pathways',
        'total_art',
        'manual_verify',
        'littercoin_paid',
        'created_by',
        'total_dogshit',
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

    public function state()
    {
        return $this->belongsTo(State::class);
    }

    public function users()
    {
        return $this->hasMany('App\Models\Users\User');
    }
}
