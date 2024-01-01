<?php

namespace App\Models\Location;

use App\Models\User\User;
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
        'user_id_last_uploaded'
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
        'brands_data',
        'ppm',
        'updatedAtDiffForHumans',
        'total_ppm'
    ];

    /**
     * Return the total photo per month for each country
     */
    public function getTotalPpmAttribute ()
    {
        $ppm = Redis::hgetall("totalppm:country:$this->id");

        return sort_ppm($ppm);
    }

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
     * Get the Photos Per Month attribute,
     *
     * Return sorted keys
     *
     * or empty array
     */
    public function getPpmAttribute ()
    {
        $ppm = Redis::hgetall("ppm:country:$this->id");

        return sort_ppm($ppm);
    }

    /**
     * Get updatedAtDiffForHumans
     */
    public function getUpdatedAtDiffForHumansAttribute () {
        return $this->updated_at->diffForHumans();
    }

    /**
     * Define relationships
     */
    public function photos () {
        return $this->hasMany(Photo::class);
    }

    // change this to firstUploader
    public function creator () {
        return $this->belongsTo(User::class, 'created_by');
    }

    // The last user_id who uploaded
    public function lastUploader () {
        return $this->belongsTo(User::class, 'user_id_last_uploaded');
    }

    public function states () {
        return $this->hasMany(State::class);
    }

    public function cities () {
        return $this->hasMany(City::class);
    }

    public function users () {
        return $this->hasMany(User::class);
    }
}
