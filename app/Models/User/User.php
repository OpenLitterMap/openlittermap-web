<?php

namespace App\Models\User;

use App\Payment;
use App\Models\Photo;
use App\Models\CustomTag;
use App\Models\Teams\Team;
use App\Models\Littercoin;
use App\Models\AI\Annotation;
use App\Models\Cleanups\Cleanup;
use App\Models\Cleanups\CleanupUser;

use Laravel\Cashier\Billable;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use LaravelAndVueJS\Traits\LaravelPermissionToVueJS;

use Illuminate\Support\Facades\Redis;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @property Collection<Team> | array<Team> $teams
 * @property Team $team
 * @property int $active_team
 * @property int $xp
 * @property int $xp_redis
 * @property bool $picked_up
 * @property array $settings
 * @property array $social_links
 */
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
        'prevent_others_tagging_my_photos',
        'littercoin_owed',
        'littercoin_paid',
        'count_correctly_verified',
        'previous_tags',
        'remaining_teams',
        'photos_per_month',
        'bbox_verification_count',
        'enable_admin_tagging'
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
        'show_username' => 'boolean',
        'verification_required' => 'boolean',
        'prevent_others_tagging_my_photos' => 'boolean',
        'settings' => 'array'
    ];

    protected $appends = [
        'total_categories',
        'total_tags',
        'total_brands_redis',
        'picked_up',
        'user_verification_count',
        'littercoin_progress',
        'total_littercoin'
    ];

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
     * Returns true if the user is verified
     * or is part of a trusted team
     */
    public function getIsTrustedAttribute(): bool
    {
        return !$this->verification_required || $this->team && $this->team->is_trusted;
    }

    /**
     * Wrapper around default setting for items_remaining,
     * for better readability
     */
    public function getPickedUpAttribute ()
    {
        return !$this->items_remaining;
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
        $totalCustomTags = $this->customTags()->count();

        return $totalLitter + $totalBrands + $totalCustomTags;
    }

    /**
     * Return the users progress to becoming a verified user
     *
     * 0-100
     */
    public function getUserVerificationCountAttribute ()
    {
        return Redis::hget("user_verification_count", $this->id) ?? 0;
    }

    /**
     * Get xp_redis attribute
     *
     * This will get the users Total Global XP.
     *
     * @return int user's total XP
     */
    public function getXpRedisAttribute()
    {
        return (int) Redis::zscore("xp.users", $this->id);
    }

    /**
     * Get this Users XP from the Global Leaderboard of All Users
     */
    public function getTodaysXpAttribute ()
    {
        $year = now()->year;
        $month = now()->month;
        $day = now()->day;

        return (int) Redis::zscore("leaderboard:users:$year:$month:$day", $this->id);
    }

    public function getYesterdaysXpAttribute ()
    {
        $year = now()->subDays(1)->year;
        $month = now()->subDays(1)->month;
        $day = now()->subDays(1)->day;

        return (int) Redis::zscore("leaderboard:users:$year:$month:$day", $this->id);
    }

    public function getThisMonthsXpAttribute ()
    {
        $year = now()->year;
        $month = now()->month;

        return (int) Redis::zscore("leaderboard:users:$year:$month", $this->id);
    }

    public function getLastMonthsXpAttribute ()
    {
        $year = now()->subMonths(1)->year;
        $month = now()->subMonths(1)->month;

        return (int) Redis::zscore("leaderboard:users:$year:$month", $this->id);
    }

    public function getThisYearsXpAttribute ()
    {
        $year = now()->year;

        return (int) Redis::zscore("leaderboard:users:$year", $this->id);
    }

    public function getLastYearsXpAttribute ()
    {
        $year = now()->year;

        return (int) Redis::zscore("leaderboard:users:$year", $this->id);
    }

    /**
     * Get the Users XP for a Location, by Time
     *
     * Here, we have to pass the locationType and locationId dynamically.
     */
    public function getXpWithParams ($param): int
    {
        $timeFilter = $param['timeFilter'];
        $locationType = $param['locationType'];
        $locationId = $param['locationId'];

        if ($timeFilter === "today")
        {
            $year = now()->year;
            $month = now()->month;
            $day = now()->day;

            // country, state, city. not users
            return (int) Redis::zscore("leaderboard:$locationType:$locationId:$year:$month:$day", $this->id);
        }
        else if ($timeFilter === "yesterday")
        {
            $year = now()->subDays(1)->year;
            $month = now()->subDays(1)->month;
            $day = now()->subDays(1)->day;

            return (int) Redis::zscore("leaderboard:$locationType:$locationId:$year:$month:$day", $this->id);
        }
        else if ($timeFilter === "this-month")
        {
            $year = now()->year;
            $month = now()->month;

            return (int) Redis::zscore("leaderboard:$locationType:$locationId:$year:$month", $this->id);
        }
        else if ($timeFilter === "last-month")
        {
            $year = now()->subMonths(1)->year;
            $month = now()->subMonths(1)->month;

            return (int) Redis::zscore("leaderboard:$locationType:$locationId:$year:$month", $this->id);
        }
        else if ($timeFilter === "this-year")
        {
            $year = now()->year;

            return (int) Redis::zscore("leaderboard:$locationType:$locationId:$year", $this->id);
        }
        else if ($timeFilter === "last-year")
        {
            $year = now()->year;

            return (int) Redis::zscore("leaderboard:$locationType:$locationId:$year", $this->id);
        }
        else if ($timeFilter === 'all-time')
        {
            return (int) Redis::zscore("leaderboard:$locationType:$locationId:total", $this->id);
        }

        return 0;
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

    public function getPositionAttribute()
    {
        return User::where('xp', '>', $this->xp ?? 0)->count() + 1;
    }

    /**
     * Return the users progress to earning their next Littercoin
     */
    public function getLittercoinProgressAttribute ()
    {
        return (int) Redis::hget("user:{$this->id}", 'littercoin_progress') ?? 0;
    }

    /**
     * Get the total number of Littercoin the user has earned
     */
    public function getTotalLittercoinAttribute ()
    {
        $count = $this->littercoin_allowance + $this->littercoin_owed;

        $count2 = Littercoin::where('user_id', $this->id)->count();

        return $count + $count2;
    }

    /**
     * Get all payments
     */
    public function payments (): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get all photos
     */
    public function photos (): HasMany
    {
        return $this->hasMany(Photo::class);
    }

    /**
     * A user can add many bounding boxes
     */
    public function boxes (): HasMany
    {
        return $this->hasMany(Annotation::class, 'added_by');
    }

    /**
     * A user can verify many bounding boxes
     */
    public function boxesVerified (): HasMany
    {
        return $this->hasMany(Annotation::class, 'verified_by');
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
    public function customTags (): HasManyThrough
    {
        return $this->hasManyThrough(CustomTag::class, Photo::class);
    }

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
     * @return BelongsTo
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
    public function teams (): BelongsToMany
    {
        return $this->belongsToMany(Team::class)
            ->withTimestamps()
            ->withPivot(
                'show_name_maps',
                'show_username_maps',
                'show_name_leaderboards',
                'show_username_leaderboards',
                'total_photos',
                'total_litter'
            );
    }

    /**
     * Shows whether the user is a member of a team or not
     */
    public function isMemberOfTeam(int $teamId): bool
    {
        return $this->teams()->where('team_id', $teamId)->exists();
    }

    /**
     * The user can be a part of many Cleanups
     *
     * Cleanups pivot table Relationships
     *
     * Load extra columns on the pivot table
     * ->withTimestamps();
     */
    public function cleanups (): BelongsToMany
    {
        return $this->belongsToMany(Cleanup::class)
            ->using(CleanupUser::class);
    }

    /**
     * Retrieve a setting with a given name or fall back to the default.
     */
    public function setting(string $name, $default = null)
    {
        if (array_key_exists($name, $this->settings ?? [])) {
            return $this->settings[$name];
        }

        return $default;
    }

    /**
     * Update one or more settings and save the model.
     */
    public function settings(array $revisions): self
    {
        $this->settings = array_merge($this->settings ?? [], $revisions);
        $this->save();

        return $this;
    }

    public function getSocialLinksAttribute(): array
    {
        return array_filter([
            'personal' => $this->setting('social_personal'),
            'twitter' => $this->setting('social_twitter'),
            'facebook' => $this->setting('social_facebook'),
            'instagram' => $this->setting('social_instagram'),
            'linkedin' => $this->setting('social_linkedin'),
            'reddit' => $this->setting('social_reddit'),
        ]);
    }
}
