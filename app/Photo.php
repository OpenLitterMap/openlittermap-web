<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Photo extends Model
{
	/*
	* Only these categories can be edited in the database
	*/
    protected $fillable = [
    	'filename', 
    	'model', 
    	'datetime', 
    	'lat', 'lon', 
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
        'bounding_box'
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

    public function owner () {
    	return $this->belongsTo(User::class, 'user_id');
    }

    public function country () {
    	return $this->hasOne('App\Country');
    }

    public function state () {
        return $this->hasOne('App\State');
    }
    
    public function city () {
    	return $this->hasOne('App\City');
    }

    public function smoking () {
    	return $this->hasOne('App\Categories\Smoking', 'id', 'smoking_id');
    }

    public function food () {
    	return $this->hasOne('App\Categories\Food', 'id', 'food_id');
    }

    public function coffee () {
    	return $this->hasOne('App\Categories\Coffee', 'id', 'coffee_id');
    }

    public function softdrinks () {
    	return $this->hasOne('App\Categories\SoftDrinks', 'id', 'softdrinks_id');
	}

	public function alcohol () {
		return $this->hasOne('App\Categories\Alcohol', 'id', 'alcohol_id');
	}

	public function sanitary () {
		return $this->hasOne('App\Categories\Sanitary', 'id', 'sanitary_id');
	}

    public function dumping () {
        return $this->hasOne('App\Categories\Dumping', 'id', 'dump_id');
    }

	public function other () {
		return $this->hasOne('App\Categories\Other', 'id', 'other_id');
	}

    public function industrial () {
        return $this->hasOne('App\Categories\Industrial', 'id', 'industrial_id');
    }

    public function coastal () {
        return $this->hasOne('App\Categories\Coastal', 'id', 'coastal_id');
    }

    public function art () {
        return $this->hasOne('App\Categories\Art', 'id', 'art_id');
    }

    public function brands () {
        return $this->hasOne('App\Categories\Brand', 'id', 'brands_id');
    }

    public function trashdog () {
        return $this->hasOne('App\Categories\TrashDog', 'id', 'trashdog_id');
    }

    // public function politics() {
    //     return $this->hasOne('App\Categories\Politicals', 'id', 'political_id');
    // }
}
