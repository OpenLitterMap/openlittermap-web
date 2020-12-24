<?php

namespace App\Models\User;

use App\Models\Photo;
use App\Models\Teams\Team;
use App\Payment;
use App\Role;
//use App\Billing\Billable;
use Laravel\Cashier\Billable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    use Notifiable, Billable, HasApiTokens;

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
        // eg. after a record has been created

        // When a user is created, add token
        // function accepts the user
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
        'level',
        'total_images',
        'total_needles',
        'total_wipes',
        'total_spoons',
        'total_bottles',
        // 'total_food',
        'total_tinfoil',
        'total_packaging',
        'total_tops',
        'total_empty',
        'total_fullpackage',
        'stripe_id',
        'images_remaining',
        'verify_remaining',
        'has_uploaded',
        'total_verified',
        'total_litter',
        'total_verified_litter',
        'total_coastal',
        'total_pathways',
        'total_art',
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
        'remaining_teams'
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

    /**
     *
     */
    public function payments ()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * The user has many photos
     */
    public function photos ()
    {
        return $this->hasMany(Photo::class);
    }

    /**
     * The user belongs to a role (specified in db)
     */
    public function role ()
    {
        return $this->belongsTo('App\Role');
    }

    /**
     * Function to check if the user is admin
     */
    public function isAdmin ()
    {
        if ($this->role->name == "Zephyr") return true;

        return false;
    }

// old stripe -> new moved to App\Billing
// protected $plan;

// // protected $token;
// /*
// * Check if the User is subscribed:
// * -> Accept a plan (not required)
// * @param string $plan
// */
// public function subscribed($plan = null) {
//     // return $this->subscribed;
//     // return $this->stripe_id;

//     // if we pass through a plan, return the plan the user is currently on
//     if ($plan) {
//         return $this->plan = $plan;
//     }
//     // else, make sure the user has a subscription -> as Bool
//     return !! $this->plan;
// }


    /**
     * Get the registered user to confirm their email
     */
    public function confirmEmail ()
    {
        $this->verified = true;
        $this->token = null;
        $this->save();
        return $this->verified;
    }

    /**
     ** A Mutator
     * Automatic hashing
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

    public function dumping ()
    {
        return $this->hasManyThrough('App\Dumping', 'App\Models\Photo');
    }

    public function food ()
    {
        return $this->hasManyThrough('App\Food', 'App\Models\Photo');
    }

    public function industrial ()
    {
        return $this->hasManyThrough('App\Industrial', 'App\Models\Photo');
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
     * Location Relationships
     ** - Get a list of all locations a User has created.
     */
    // public function locations_created() {
    //     $countries = \App\Models\Location\Country::where('manual_verify', 1)->get();
    //     $countries_created = [];
    //     foreach($countries as $country) {
    //         if($country->photos->first()->user_id == $this->id) {
    //             array_push($countries_created, [$country->name, $country->id]);
    //         }
    //     }
    //     $countries_created = json_encode($countries_created);
    //     return $countries_created;
    // }

    /**
     * Give the user experience points
     */
//    public function givePoints (n)
//    {
//        $this->increment('xp', n);
//    }

    /**
     * Currently active team
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
