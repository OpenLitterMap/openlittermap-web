<?php

namespace App\Models\User;

use App\Models\Photo;
use App\Models\Teams\Team;
use App\Payment;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Redis;
use Laravel\Cashier\Billable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;
use Laravel\Passport\HasApiTokens;
use LaravelAndVueJS\Traits\LaravelPermissionToVueJS;

class User extends Authenticatable
{
    use Notifiable, Billable, HasApiTokens, HasRoles, LaravelPermissionToVueJS, HasFactory;

    /**
     * On creation, give a new user a 30 random string for email verification
     * Model event:
     * triggered automatically
     */
    public static function boot ()
    {
        // trigger the boot method of the Model Class that Eloquent models extend
        parent::boot();

        // listen for model events
        // When a user is created, add tokens
        static::creating(function($user) {
            $user->token = str_random(30);
        });

        static::creating(function($user) {
            $user->sub_token = str_random(30);
        });

        static::addGlobalScope('photosCount', function($builder) {
            $builder->withCount('photos'); // photos_count
        });
    }

    /**
     * Eager load by default
     */
     protected $with = ['team'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'verified',
        'password',
        'email',
        'username',
        'plan',
        'xp',
        'total_images',
        'level',
        'show_name',
        'show_username',

        'stripe_id',
        'images_remaining',
        'verify_remaining',
        'has_uploaded',

        'emailsub',
        'sub_token',
        'eth_wallet',
        'littercoin_allowance',
        'has_uploaded_today',
        'has_uploaded_counter',
        'active_team',
        'link_instagram',
        'verification_required',
        'littercoin_owed',
        'littercoin_paid',
        'count_correctly_verified',
        'previous_tags',
        'remaining_teams',
        'photos_per_month',
        'bbox_verification_count'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token', 'role_id'
    ];

    protected $guarded = [
        'role_id'
    ];

    protected $casts = [
        'show_name' => 'boolean',
        'show_username' => 'boolean'
    ];

    protected $appends = ['total_categories', 'total_tags', 'total_brands_redis'];

    /**
     * Get total categories attribute
     *
     * @return array
     */
    public function getTotalCategoriesAttribute ()
    {
        $categories = Photo::categories();

        $totals = [];

        foreach ($categories as $category)
        {
            if ($category !== "brands")
            {
                $totals[$category] = (int)Redis::hget("user:$this->id", $category);
            }
        }

        return $totals;
    }

    /**
     * Get total tags attribute
     *
     * @return int total number of tags
     */
    public function getTotalTagsAttribute ()
    {
        $totalBrands = (int) Redis::hget("user:{$this->id}", 'total_brands');
        $totalLitter = (int) Redis::hget("user:{$this->id}", 'total_litter');

        return $totalLitter + $totalBrands;
    }

    /**
     * Get total brand tags from Redis
     *
     * @return int total number of brand tags
     */
    public function getTotalBrandsRedisAttribute ()
    {
        return (int) Redis::hget("user:{$this->id}", 'total_brands');
    }

    /**
     * Get all payments
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function payments ()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get all photos
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function photos ()
    {
        return $this->hasMany(Photo::class);
    }

    /**
     * Get the registered user to confirm their email
     *
     * return boolean
     */
    public function confirmEmail ()
    {
        $this->verified = true;
        $this->token = null;
        $this->save();
        return $this->verified;
    }

    /**
     * A Mutator - Automatic hashing
     *
     * return void
     */
    public function setPasswordAttribute ($password)
    {
        $this->attributes['password'] = bcrypt($password);
    }

    /**
     * Has Many Through relationships
     */
    public function smoking ()
    {
        return $this->hasManyThrough('App\Smoking', 'App\Models\Photo');
    }

    public function alcohol ()
    {
        return $this->hasManyThrough('App\Alcohol', 'App\Models\Photo');
    }

    public function coffee ()
    {
        return $this->hasManyThrough('App\Coffee', 'App\Models\Photo');
    }

    public function food ()
    {
        return $this->hasManyThrough('App\Food', 'App\Models\Photo');
    }

    public function softdrinks ()
    {
        return $this->hasManyThrough('App\SoftDrinks', 'App\Models\Photo');
    }

    public function drugs ()
    {
        return $this->hasManyThrough('App\Drugs', 'App\Models\Photo');
    }

    public function sanitary ()
    {
        return $this->hasManyThrough('App\Sanitary', 'App\Models\Photo');
    }

    public function other ()
    {
        return $this->hasManyThrough('App\Other', 'App\Models\Photo');
    }

    public function coastal ()
    {
        return $this->hasManyThrough('App\Coastal', 'App\Models\Photo');
    }

    public function pathway ()
    {
        return $this->hasManyThrough('App\Pathway', 'App\Models\Photo');
    }

    public function art ()
    {
        return $this->hasManyThrough('App\Art', 'App\Models\Photo');
    }

    /**
     * Currently active team
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function team ()
    {
        return $this->belongsTo(Team::class, 'active_team', 'id');
    }

    /**
     * Team Relationships
     *
     * Load extra columns on the pivot table
     */
    public function teams ()
    {
        return $this->belongsToMany(Team::class)
            ->withPivot(
                'show_name_maps',
                'show_username_maps',
                'show_name_leaderboards',
                'show_username_leaderboards'
            );
    }


}
