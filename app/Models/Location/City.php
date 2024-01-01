<?php

namespace App\Models\Location;

use App\Models\User\User;
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
     * Extra columns on our Country model
     */
    protected $appends = [
        'total_litter_redis',
        'total_photos_redis',
        'total_contributors_redis',
        'litter_data',
        'brands_data',
        'ppm',
        'updatedAtDiffForHumans',
        'total_ppm'
    ];

    /**
     * Return the total photo per month for each city
     */
    public function getTotalPpmAttribute ()
    {
        $ppm = Redis::hgetall("totalppm:city:$this->id");

        return sort_ppm($ppm);
    }


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

    /**
     * Get the Photos Per Month attribute,
     *
     * Return sorted keys
     *
     * or empty array
     */
    public function getPpmAttribute ()
    {
        $ppm = Redis::hgetall("ppm:city:$this->id");

        return sort_ppm($ppm);
    }

    /**
     * Get updatedAtDiffForHumans
     */
    public function getUpdatedAtDiffForHumansAttribute () {
        return $this->updated_at->diffForHumans();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lastUploader () {
        return $this->belongsTo(User::class, 'user_id_last_uploaded');
    }

    public function country() {
        return $this->belongsTo(Country::class);
    }

    public function state() {
        return $this->belongsTo(State::class);
    }

    public function photos() {
        return $this->hasMany(Photo::class);
    }

    public function users() {
        return $this->hasMany(User::class);
    }
}
