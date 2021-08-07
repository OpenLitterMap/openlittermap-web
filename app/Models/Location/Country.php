<?php

namespace App\Models\Location;

use App\Models\Photo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Redis;

class Country extends Location
{
    use HasFactory;

    /**
     * Properties that are mass assignable
     */
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
        'photos_per_month'
    ];

    public function getRouteKeyName()
    {
        return 'country';
    }

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
        return Redis::hexists("country:$this->id", "total_litter")
            ? (int)Redis::hget("country:$this->id", "total_litter")
            : 0;
    }

    /**
     * Return the total_photos value from redis
     */
    public function getTotalPhotosRedisAttribute ()
    {
        return Redis::hexists("country:$this->id", "total_photos")
            ? (int)Redis::hget("country:$this->id", "total_photos")
            : 0;
    }

    /**
     * Return the total number of people who uploaded a photo from redis
     */
    public function getTotalContributorsRedisAttribute ()
    {
        return Redis::scard("country:$this->id:user_ids");
    }

    /**
     * Return array of total_category => value
     *
     * for country:id total_category
     */
    public function getLitterDataAttribute ()
    {
        $categories = Photo::categories();

        $totals = [];

        foreach ($categories as $category)
        {
            if ($category !== "brands")
            {
                $totals[$category] = Redis::hget("country:$this->id", $category);
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
     * Define relationships
     */
    public function photos () {
        return $this->hasMany('App\Models\Photo');
    }

    public function creator () {
        return $this->belongsTo('App\Models\User\User', 'created_by');
    }

    public function states () {
        return $this->hasMany('App\Models\Location\State');
    }

    public function cities () {
        return $this->hasMany('App\Models\Location\City');
    }

    public function users () {
        return $this->hasMany('App\Models\User\User');
    }
}
