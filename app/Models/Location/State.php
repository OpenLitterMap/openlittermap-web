<?php

namespace App\Models\Location;

use App\Events\NewStateAdded;
use App\Models\Photo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Redis;

class State extends Location
{
    use HasFactory;

    protected $fillable = [
		'id',
		'state',
		'country_id',
		'created_at',
		'updated_at',
		'manual_verify',
        'littercoin_paid',
        'created_by',
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
        return Redis::hexists("state:$this->id", "total_litter")
            ? (int)Redis::hget("state:$this->id", "total_litter")
            : 0;
    }

    /**
     * Return the total_photos value from redis
     */
    public function getTotalPhotosRedisAttribute ()
    {
        return Redis::hexists("state:$this->id", "total_photos")
            ? (int)Redis::hget("state:$this->id", "total_photos")
            : 0;
    }

    /**
     * Return the total number of people who uploaded a photo from redis
     */
    public function getTotalContributorsRedisAttribute ()
    {
        return Redis::scard("state:$this->id:user_ids");
    }

    /**
     * Return array of total_category => value
     *
     * for state:id total_category
     */
    public function getLitterDataAttribute ()
    {
        $categories = Photo::categories();

        $totals = [];

        foreach ($categories as $category)
        {
            if ($category !== "brands")
            {
                $totals[$category] = Redis::hget("state:$this->id", $category);
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

    /**
     * Relationships
     */
	public function creator () {
		return $this->belongsTo('App\Models\User\User', 'created_by');
	}

	public function country () {
		return $this->belongsTo('App\Models\Location\Country');
	}

    public function cities () {
    	return $this->hasMany('App\Models\Location\City');
    }

    public function photos () {
    	return $this->hasMany('App\Models\Photo');
    }

    /**
     * Return State.id from $addressArray
     *
     * @param array $addressArray
     * @param $country
     */
    public static function getStateFromAddressArray (Country $country, array $addressArray)
    {
        $stateName = null;

        // Extract state name to get state.id
        if (array_key_exists('state', $addressArray))
        {
            $stateName = $addressArray["state"];
        }
        if (!$stateName)
        {
            if (array_key_exists('county', $addressArray))
            {
                $stateName = $addressArray["county"];
            }
        }
        if (!$stateName)
        {
            if (array_key_exists('region', $addressArray))
            {
                $stateName = $addressArray["region"];
            }
        }
        if (!$stateName)
        {
            $stateName = 'error';
        }

        if ($stateName !== 'error')
        {
            try
            {
                $state = State::select('id', 'country_id', 'state', 'statenameb')
                    ->where([
                        'state' => $stateName,
                        'country_id' => $country->id
                    ])
                    ->firstOrCreate();

                if ($state->wasRecentlyCreated)
                {
                    // Broadcast an event to anyone viewing the Global Map
                    event(new NewStateAdded($stateName, $country->country, now()));
                }

                return $state;
            }
            catch (\Exception $e)
            {
                \Log::info(['CheckLocations.checkState', $e->getMessage()]);
            }
        }

        // Return error state
        return State::where('state', 'error_state')->first();
    }
}
