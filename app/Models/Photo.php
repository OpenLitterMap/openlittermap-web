<?php

namespace App\Models;

use App\Models\AI\Annotation;
use App\Models\Teams\Team;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Photo extends Model
{
    use HasFactory;

    protected $fillable = [
    	'filename',
    	'model',
    	'datetime',
    	'lat',
        'lon',
        'verification',
        'verified',
        'result_string',
        'total_litter',

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
        'sanitary_id',
        'industrial_id',
        'other_id',
        'coastal_id',
        'art_id',
        'brands_id',
        'trashdog_id',
        'dogshit_id',

        'platform',
        'bounding_box',
        'geohash',
        'team_id',

        // annotations
        'bbox_skipped',
        'skipped_by',
        'bbox_assigned_to',
        'wrong_tags',
        'wrong_tags_by',
        'bbox_verification_assigned_to',

        // Introduced after resizing images to 500x500
        'five_hundred_square_filepath',
        'bbox_500_assigned_to',

        'address_array'
    ];

    protected $appends = ['selected'];

    protected $casts = ['datetime'];

    /**
     * Create an Accessor that adds ['selected' => false] to each order
     * The user can select an order to export it
     */
    public function getSelectedAttribute ()
    {
        return false;
    }

    /**
     * A photo can have many bounding boxes associated with it
     */
    public function boxes ()
    {
        return $this->hasMany(Annotation::class);
    }

    /**
     * All Categories
     */
    public static function categories ()
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
            'brands',
            'dogshit'
        ];
    }

    /**
     * All Currently available Brands
     */
    public static function getBrands ()
    {
        return [
            'adidas',
            'amazon',
            'apple',
            'applegreen',
            'avoca',
            'bewleys',
            'brambles',
            'butlers',
            'budweiser',
            'cafe_nero',
            'centra',
            'coke',
            'colgate',
            'corona',
            'costa',
            'esquires',
            'frank_and_honest',
            'fritolay',
            'gillette',
            'heineken',
            'insomnia',
            'kellogs',
            'lego',
            'lolly_and_cookes',
            'loreal',
            'nescafe',
            'nestle',
            'marlboro',
            'mcdonalds',
            'nike',
            'obriens',
            'pepsi',
            'redbull',
            'samsung',
            'subway',
            'supermacs',
            'starbucks',
            'tayto',
            'wilde_and_greene'
        ];
    }

    /**
     * User who uploaded the photo
     *
     * This is unnecessarily loading
     * - photos_count
     * - team
     * - total_categories
     */
    public function user ()
    {
    	return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Team that uploaded the photo
     */
    public function team ()
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    /**
     * Return the tags for an image
     *
     * Remove any keys with null values
     */
    public function tags ()
    {
        foreach ($this->categories() as $category)
        {
            if ($this->$category)
            {
                foreach ($this->$category->types() as $tag)
                {
                    if (is_null($this->$category[$tag]))
                    {
                        unset ($this->$category[$tag]);
                    }
                }
            }
        }
    }

    /**
     * Update and return the total amount of litter in a photo
     */
    public function total ()
    {
        $total = 0;

        foreach ($this->categories() as $category)
        {
            if ($this->$category)
            {
                // We dont want to include brands in total_litter
                // Increment total_litter when its not brands
                if ($category !== 'brands')
                {
                    $total += $this->$category->total();
                }
            }
        }

        $this->total_litter = $total;
        $this->save();
    }

    /**
     * Save translation key => value for every item on each category that has a value
     *
     * Format: category.item quantity, category.item quantity,
     *
     * eg. smoking.butts 3, alcohol.beerBottles 4,
     *
     * We use the result_string on the global map for 2 reasons.
     * 1. We don't have to eager load any data.
     * 2. This format can be translated into any language.
     */
    public function translate ()
    {
        $result_string = '';

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

    public function dogshit ()
    {
        return $this->hasOne('App\Models\Litter\Categories\Dogshit', 'id', 'dogshit_id');
    }

    // public function politics() {
    //     return $this->hasOne('App\Models\Litter\Categories\Politicals', 'id', 'political_id');
    // }
}
