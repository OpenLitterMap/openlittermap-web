<?php

namespace App\Models;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;

class Photo extends Model
{
    protected $fillable = [
    	'filename',
    	'model',
    	'datetime',
    	'lat',
        'lon',
        'verification',
        'result_string',

    	'display_name',
    	'location',
    	'road',
    	'suburb',
    	'city',
    	'county',
    	'state_district',
    	'country',
    	'country_code',

        'city_id',
        'state_id',
        'country_id',

    	'smoking_id',
        'alcohol_id',
        'coffee_id',
    	'food_id',
    	'softdrinks_id',
        'dumping_id',
        'drugs_id',
        'sanitary_id',
        'industrial_id',
        'other_id',
        'coastal_id',
        // 'pathways_id',
        'art_id',
        'brands_id',
        'trashdog_id',
        'total_litter',
        'platform',
        'bounding_box',
        'geohash',
        'team_id'
    ];

    /**
     * Observe when this model is being updated
    - onDelete, also delete relationships
    - onDelete->cascade not working
     */
    public static function boot ()
    {
        parent::boot();

        self::deleting(function (Photo $photo) {
            if ($photo->smoking) $photo->smoking->delete();
            if ($photo->food) $photo->food->delete();
            if ($photo->coffee) $photo->coffee->delete();
            if ($photo->softdrinks) $photo->softdrinks->delete();
            if ($photo->alcohol) $photo->alcohol->delete();
            if ($photo->sanitary) $photo->sanitary->delete();
            if ($photo->other) $photo->other->delete();
            if ($photo->coastal) $photo->coastal->delete();
            if ($photo->art) $photo->art->delete();
            if ($photo->brands) $photo->brands->delete();
            if ($photo->trashdog) $photo->trashdog->delete();
            if ($photo->dumping) $photo->dumping->delete();
            if ($photo->industrial) $photo->industrial->delete();
        });
    }

    /**
     * Category types available on each photo
     */
    public function categories ()
    {
        return [
            'smoking',
            'food',
            'coffee',
            'softdrinks',
            'alcohol',
            'other',
            'coastal',
            'sanitary',
            'dumping',
            'industrial',
            'brands'
        ];
    }

    /**
     * User who uploaded the photo
     */
    public function owner ()
    {
    	return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Save translation key => value for every item on each category that has a value
     */
    public function translate ()
    {
        $result_string = ''; // smoking.butts 3, alcohol.beerBottles 4,

        foreach ($this->categories() as $category)
        {
            if ($this->$category)
            {
                $result_string .= $this->$category->translate();
            }
        }

        $this->result_string = $result_string;
        $this->save();
    }

    /**
     * Location relationships
     */
    public function country ()
    {
    	return $this->hasOne('App\Models\Location\Country');
    }

    public function state ()
    {
        return $this->hasOne('App\Models\Location\State');
    }

    public function city ()
    {
    	return $this->hasOne('App\Models\Location\City');
    }

    /**
     * Litter categories
     */
    public function smoking ()
    {
    	return $this->hasOne('App\Models\Litter\Categories\Smoking', 'id', 'smoking_id');
    }

    public function food ()
    {
    	return $this->hasOne('App\Models\Litter\Categories\Food', 'id', 'food_id');
    }

    public function coffee ()
    {
    	return $this->hasOne('App\Models\Litter\Categories\Coffee', 'id', 'coffee_id');
    }

    public function softdrinks ()
    {
    	return $this->hasOne('App\Models\Litter\Categories\SoftDrinks', 'id', 'softdrinks_id');
	}

	public function alcohol ()
    {
		return $this->hasOne('App\Models\Litter\Categories\Alcohol', 'id', 'alcohol_id');
	}

	public function sanitary ()
    {
		return $this->hasOne('App\Models\Litter\Categories\Sanitary', 'id', 'sanitary_id');
	}

    public function dumping ()
    {
        return $this->hasOne('App\Models\Litter\Categories\Dumping', 'id', 'dumping_id');
    }

	public function other ()
    {
		return $this->hasOne('App\Models\Litter\Categories\Other', 'id', 'other_id');
	}

    public function industrial ()
    {
        return $this->hasOne('App\Models\Litter\Categories\Industrial', 'id', 'industrial_id');
    }

    public function coastal ()
    {
        return $this->hasOne('App\Models\Litter\Categories\Coastal', 'id', 'coastal_id');
    }

    public function art ()
    {
        return $this->hasOne('App\Models\Litter\Categories\Art', 'id', 'art_id');
    }

    public function brands ()
    {
        return $this->hasOne('App\Models\Litter\Categories\Brand', 'id', 'brands_id');
    }

    public function trashdog ()
    {
        return $this->hasOne('App\Models\Litter\Categories\TrashDog', 'id', 'trashdog_id');
    }

    // public function politics() {
    //     return $this->hasOne('App\Models\Litter\Categories\Politicals', 'id', 'political_id');
    // }
}
