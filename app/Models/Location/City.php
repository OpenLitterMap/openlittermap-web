<?php

namespace App\Models\Location;

use App\Events\NewCityAdded;
use App\Models\Photo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Redis;

class City extends Location
{
    use HasFactory;

	protected $fillable = [
		'id',
		'city',
		'country_id',
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
        'total_dogshit'
	];

	/**
     * Extra columns on our Country model
     */
    protected $appends = [
        'total_litter_redis',
        'total_photos_redis',
        'total_contributors_redis',
        'litter_data',
        'brands_data'
    ];

    /**
     * Return the total_litter value from redis
     */
    public function getTotalLitterRedisAttribute ()
    {
        return Redis::hexists("city:$this->id", "total_litter")
            ? (int)Redis::hget("city:$this->id", "total_litter")
            : 0;
    }

    /**
     * Return the total_photos value from redis
     */
    public function getTotalPhotosRedisAttribute ()
    {
        return Redis::hexists("city:$this->id", "total_photos")
            ? (int)Redis::hget("city:$this->id", "total_photos")
            : 0;
    }

    /**
     * Return the total number of people who uploaded a photo from redis
     */
    public function getTotalContributorsRedisAttribute ()
    {
        return Redis::scard("city:$this->id:user_ids");
    }

    /**
     * Return array of total_category => value
     *
     * for city:id total_category
     */
    public function getLitterDataAttribute ()
    {
        $categories = Photo::categories();

        $totals = [];

        foreach ($categories as $category)
        {
            if ($category !== "brands")
            {
                $totals[$category] = Redis::hget("city:$this->id", $category);
            }
        }

        return $totals;
    }

    /**
     * Return array of brand_total => value
     */
    public function getBrandsDataAttribute ()
    {
        $brands = Photo::getBrands();

        $totals = [];

        foreach ($brands as $brand)
        {
            $totals[$brand] = Redis::hget("country:$this->id", $brand);
        }

        return $totals;
    }

	public function creator()
	{
		return $this->belongsTo('App\Models\User\User', 'created_by');
	}

    public function country() {
    	return $this->belongsTo('App\Models\Location\Country');
    }

    public function state() {
    	return $this->belongsTo('App\Models\Location\State');
    }

    public function photos() {
    	return $this->hasMany('App\Models\Photo');
    }

    public function users() {
    	return $this->hasMany('App\Models\User\User');
    }

    /**
     * Return a city from Country, State, addressArrray
     */
    public function getCityFromAddressArray (Country $country, State $state, $addressArray)
    {
        $cityName = null;

        if (array_key_exists('city', $addressArray))
        {
            $cityName = $addressArray['city'];
        }
        if (!$cityName)
        {
            if (array_key_exists('town', $addressArray))
            {
                $cityName = $addressArray['town'];
            }
        }
        if (!$cityName)
        {
            if (array_key_exists('city_district', $addressArray))
            {
                $cityName = $addressArray['city_district'];
            }
        }
        if (!$cityName)
        {
            if (array_key_exists('village', $addressArray))
            {
                $cityName = $addressArray['village'];
            }
        }
        if (!$cityName)
        {
            if (array_key_exists('hamlet', $addressArray))
            {
                $cityName = $addressArray['hamlet'];
            }
        }
        if (!$cityName)
        {
            if (array_key_exists('locality', $addressArray))
            {
                $cityName = $addressArray['locality'];
            }
        }
        if (!$cityName)
        {
            if (array_key_exists('county', $addressArray))
            {
                $cityName = $addressArray['county'];
            }
        }
        if (!$cityName)
        {
            $cityName = 'error';
        }

        if ($cityName !== 'error')
        {
            try
            {
                $city = City::select('id', 'country_id', 'state_id', 'city')
                    ->where([
                        'country_id' => $country->id,
                        'state_id' => $state->id,
                        'city' => $cityName
                    ])
                    ->firstOrCreate();

                if ($city->wasRecentlyCreated)
                {
                    // Broadcast an event to anyone viewing the Global Map
                    event(new NewCityAdded($cityName, $this->state, $this->country, now()));
                }
            }
            catch (\Exception $e)
            {
                \Log::info(['CheckLocations@createCity', $e->getMessage()]);
            }
        }

        // Return error city
        return City::find(404);
    }
}
