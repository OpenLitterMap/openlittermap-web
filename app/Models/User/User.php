<?php

namespace App\Models\User;

use App\Models\AI\Annotation;
use App\Models\Category;
use App\Models\CustomTag;
use App\Models\Photo;
use App\Models\Teams\Team;
use App\Payment;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\Redis;
use Laravel\Cashier\Billable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;
use Laravel\Passport\HasApiTokens;
use LaravelAndVueJS\Traits\LaravelPermissionToVueJS;

/**
 * @property array<Team> $teams
 * @property Team $team
 * @property int $active_team
 * @property int $xp
 * @property int $xp_redis
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
        'has_uploaded_today',
        'has_uploaded_counter',
        'active_team',
        'link_instagram',
        'verification_required',
        'previous_tags',
        'remaining_teams',
        'photos_per_month',
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
        'verification_required' => 'boolean'
    ];

    protected $appends = ['total_categories', 'total_tags', 'total_brands_redis', 'picked_up'];

    /**
     * Get total categories attribute
     *
     * @return array
     */
    public function getTotalCategoriesAttribute ()
    {
        $totals = [];

        /** @var Category $category */
        foreach (Category::all() as $category) {
            $totals[$category->name] = (int)Redis::hget("user:$this->id", $category->name);
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
     * Get xp_redis attribute
     *
     * @return int user's total XP
     */
    public function getXpRedisAttribute()
    {
        return (int) Redis::zscore("xp.users", $this->id);
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
}
