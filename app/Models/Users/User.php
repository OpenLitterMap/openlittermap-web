<?php

namespace App\Models\Users;

use App\Level;
use App\Models\Achievements\Achievement;
use App\Services\LevelService;
use App\Models\Badges\Badge;
use App\Payment;
use App\Models\Photo;
use App\Models\CustomTag;
use App\Models\Teams\Team;
use App\Models\Littercoin;
use App\Models\AI\Annotation;
use App\Models\Cleanups\Cleanup;
use App\Models\Cleanups\CleanupUser;

use Laravel\Cashier\Billable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use LaravelAndVueJS\Traits\LaravelPermissionToVueJS;

use Illuminate\Support\Facades\Redis;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

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
        parent::boot();

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
     protected $with = [
         'team',
     ];

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
        'total_images', // deprecated
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
        'username_flagged',
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

    protected $attributes = [
        'picked_up' => true,
    ];

    protected $casts = [
        'verified' => 'boolean',
        // picked_up is nullable tri-state (true/false/null) — no boolean cast to preserve null
        'show_name' => 'boolean',
        'show_username' => 'boolean',
        'public_profile' => 'boolean',
        'verification_required' => 'boolean',
        'username_flagged' => 'boolean',
        'prevent_others_tagging_my_photos' => 'boolean',
        'settings' => 'array',
    ];

    protected $appends = [
        'total_categories',
        'total_tags',
        'total_brands_redis',
        'user_verification_count',
        'littercoin_progress',
        'total_littercoin',
        'next_level',
        'social_links',
    ];

    /**
     * @deprecated
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
     * @deprecated
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
     * @deprecated
     * Get this Users XP from the Global Leaderboard of All Users
     */
    public function getTodaysXpAttribute ()
    {
        $year = now()->year;
        $month = now()->month;
        $day = now()->day;

        return (int) Redis::zscore("leaderboard:users:$year:$month:$day", $this->id);
    }

    // @deprecated
    public function getYesterdaysXpAttribute ()
    {
        $year = now()->subDays(1)->year;
        $month = now()->subDays(1)->month;
        $day = now()->subDays(1)->day;

        return (int) Redis::zscore("leaderboard:users:$year:$month:$day", $this->id);
    }

    // @deprecated
    public function getThisMonthsXpAttribute ()
    {
        $year = now()->year;
        $month = now()->month;

        return (int) Redis::zscore("leaderboard:users:$year:$month", $this->id);
    }

    // @deprecated
    public function getLastMonthsXpAttribute ()
    {
        $year = now()->subMonths(1)->year;
        $month = now()->subMonths(1)->month;

        return (int) Redis::zscore("leaderboard:users:$year:$month", $this->id);
    }

    // @deprecated
    public function getThisYearsXpAttribute ()
    {
        $year = now()->year;

        return (int) Redis::zscore("leaderboard:users:$year", $this->id);
    }

    // @deprecated
    public function getLastYearsXpAttribute ()
    {
        $year = now()->year;

        return (int) Redis::zscore("leaderboard:users:$year", $this->id);
    }

    public function getNextLevelAttribute(): array
    {
        return LevelService::getUserLevel((int) $this->xp);
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

    public function achievements()
    {
        return $this->belongsToMany(
            Achievement::class,
            'user_achievements'
        );
    }

    /**
     * @deprecated
     */
    public function customTags (): HasManyThrough
    {
        return $this->hasManyThrough(CustomTag::class, Photo::class);
    }
    /**
     * @deprecated
     */
    public function smoking ()
    {
        return $this->hasManyThrough('App\Smoking', 'App\Models\Photo');
    }
    /**
     * @deprecated
     */
    public function alcohol ()
    {
        return $this->hasManyThrough('App\Alcohol', 'App\Models\Photo');
    }
    /**
     * @deprecated
     */
    public function coffee ()
    {
        return $this->hasManyThrough('App\Coffee', 'App\Models\Photo');
    }
    /**
     * @deprecated
     */
    public function food ()
    {
        return $this->hasManyThrough('App\Food', 'App\Models\Photo');
    }
    /**
     * @deprecated
     */
    public function softdrinks ()
    {
        return $this->hasManyThrough('App\SoftDrinks', 'App\Models\Photo');
    }
    /**
     * @deprecated
     */
    public function drugs ()
    {
        return $this->hasManyThrough('App\Drugs', 'App\Models\Photo');
    }
    /**
     * @deprecated
     */
    public function sanitary ()
    {
        return $this->hasManyThrough('App\Sanitary', 'App\Models\Photo');
    }
    /**
     * @deprecated
     */
    public function other ()
    {
        return $this->hasManyThrough('App\Other', 'App\Models\Photo');
    }
    /**
     * @deprecated
     */
    public function coastal ()
    {
        return $this->hasManyThrough('App\Coastal', 'App\Models\Photo');
    }
    /**
     * @deprecated
     */
    public function pathway ()
    {
        return $this->hasManyThrough('App\Pathway', 'App\Models\Photo');
    }
    /**
     * @deprecated
     */
    public function art ()
    {
        return $this->hasManyThrough('App\Art', 'App\Models\Photo');
    }

    public function badges()
    {
        return $this->belongsToMany(Badge::class, 'user_badges')->withTimestamps()->withPivot('awarded_at');
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
    public function settings (array $revisions): self
    {
        $this->settings = array_merge($this->settings ?? [], $revisions);
        $this->save();

        return $this;
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->name ?? $this->username;
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

    /**
     * @deprecated
     */
    public function analyzePhotoSummaries($limit = 100)
    {
        $photos = $this->photos()
            ->whereNotNull('summary')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get(['id', 'created_at', 'summary', 'xp', 'total_tags', 'total_brands']);

        if ($photos->isEmpty()) {
            echo "No photos found with summaries.\n";
            return;
        }

        // Initialize aggregators
        $totalStats = [
            'photo_count' => 0,
            'total_xp' => 0,
            'total_tags' => 0,
            'total_objects' => 0,
            'total_materials' => 0,
            'total_brands' => 0,
            'total_custom_tags' => 0,
            'categories' => [],
            'objects' => [],
            'materials' => [],
            'brands' => [],
            'custom_tags' => [],
            'by_category' => [],
        ];

        echo "Analyzing {$photos->count()} photos for user {$this->name} (#{$this->id})\n";
        echo str_repeat("=", 80) . "\n\n";

        // Process each photo
        foreach ($photos as $index => $photo) {
            $photoNum = $index + 1;
            $summary = $photo->summary;

            echo "Photo #{$photoNum} (ID: {$photo->id}) - Created: {$photo->created_at->format('Y-m-d H:i:s')}\n";
            echo str_repeat("-", 40) . "\n";

            // Display photo totals
            $totals = $summary['totals'] ?? [];
            echo "  Total tags: " . ($totals['total_tags'] ?? 0) . "\n";
            echo "  Total objects: " . ($totals['total_objects'] ?? 0) . "\n";
            echo "  Materials: " . ($totals['materials'] ?? 0) . "\n";
            echo "  Brands: " . ($totals['brands'] ?? 0) . "\n";
            echo "  Custom tags: " . ($totals['custom_tags'] ?? 0) . "\n";
            echo "  XP earned: {$photo->xp}\n";

            // Show category breakdown
            if (!empty($totals['by_category'])) {
                echo "  Categories: ";
                $catBreakdown = [];
                foreach ($totals['by_category'] as $cat => $count) {
                    $catBreakdown[] = "$cat($count)";
                }
                echo implode(', ', $catBreakdown) . "\n";
            }

            // Show tag details
            if (!empty($summary['tags'])) {
                echo "\n  Detailed breakdown:\n";
                foreach ($summary['tags'] as $category => $objects) {
                    foreach ($objects as $object => $data) {
                        echo "    [{$category}] {$object}: {$data['quantity']}";

                        $extras = [];
                        if (!empty($data['materials'])) {
                            $matList = [];
                            foreach ($data['materials'] as $mat => $qty) {
                                $matList[] = "$mat($qty)";
                            }
                            $extras[] = "materials: " . implode(', ', $matList);
                        }
                        if (!empty($data['brands'])) {
                            $brandList = [];
                            foreach ($data['brands'] as $brand => $qty) {
                                $brandList[] = "$brand($qty)";
                            }
                            $extras[] = "brands: " . implode(', ', $brandList);
                        }
                        if (!empty($data['custom_tags'])) {
                            $customList = [];
                            foreach ($data['custom_tags'] as $custom => $qty) {
                                $customList[] = "$custom($qty)";
                            }
                            $extras[] = "custom: " . implode(', ', $customList);
                        }

                        if (!empty($extras)) {
                            echo " - " . implode(' | ', $extras);
                        }
                        echo "\n";
                    }
                }
            }

            echo "\n";

            // Aggregate data
            $totalStats['photo_count']++;
            $totalStats['total_xp'] += $photo->xp;
            $totalStats['total_tags'] += $totals['total_tags'] ?? 0;
            $totalStats['total_objects'] += $totals['total_objects'] ?? 0;
            $totalStats['total_materials'] += $totals['materials'] ?? 0;
            $totalStats['total_brands'] += $totals['brands'] ?? 0;
            $totalStats['total_custom_tags'] += $totals['custom_tags'] ?? 0;

            // Aggregate by category
            foreach ($totals['by_category'] ?? [] as $cat => $count) {
                $totalStats['by_category'][$cat] = ($totalStats['by_category'][$cat] ?? 0) + $count;
            }

            // Aggregate individual items
            foreach ($summary['tags'] ?? [] as $category => $objects) {
                $totalStats['categories'][$category] = ($totalStats['categories'][$category] ?? 0) + 1;

                foreach ($objects as $object => $data) {
                    $totalStats['objects'][$object] = ($totalStats['objects'][$object] ?? 0) + $data['quantity'];

                    foreach ($data['materials'] ?? [] as $mat => $qty) {
                        $totalStats['materials'][$mat] = ($totalStats['materials'][$mat] ?? 0) + $qty;
                    }

                    foreach ($data['brands'] ?? [] as $brand => $qty) {
                        $totalStats['brands'][$brand] = ($totalStats['brands'][$brand] ?? 0) + $qty;
                    }

                    foreach ($data['custom_tags'] ?? [] as $custom => $qty) {
                        $totalStats['custom_tags'][$custom] = ($totalStats['custom_tags'][$custom] ?? 0) + $qty;
                    }
                }
            }
        }

        // Display total report
        echo str_repeat("=", 80) . "\n";
        echo "TOTAL REPORT FOR {$totalStats['photo_count']} PHOTOS\n";
        echo str_repeat("=", 80) . "\n\n";

        echo "Overall Statistics:\n";
        echo "  Total XP earned: " . number_format($totalStats['total_xp']) . "\n";
        echo "  Total tags: " . number_format($totalStats['total_tags']) . "\n";
        echo "  Total objects: " . number_format($totalStats['total_objects']) . "\n";
        echo "  Total materials: " . number_format($totalStats['total_materials']) . "\n";
        echo "  Total brands: " . number_format($totalStats['total_brands']) . "\n";
        echo "  Total custom tags: " . number_format($totalStats['total_custom_tags']) . "\n";
        echo "  Average tags per photo: " . round($totalStats['total_tags'] / $totalStats['photo_count'], 1) . "\n";
        echo "  Average XP per photo: " . round($totalStats['total_xp'] / $totalStats['photo_count'], 1) . "\n";

        echo "\nCategory Distribution:\n";
        arsort($totalStats['by_category']);
        foreach ($totalStats['by_category'] as $cat => $count) {
            $percentage = round(($count / $totalStats['total_tags']) * 100, 1);
            echo "  {$cat}: " . number_format($count) . " ({$percentage}%)\n";
        }

        echo "\nTop 10 Objects:\n";
        arsort($totalStats['objects']);
        $topObjects = array_slice($totalStats['objects'], 0, 10, true);
        foreach ($topObjects as $obj => $count) {
            echo "  {$obj}: " . number_format($count) . "\n";
        }

        if (!empty($totalStats['materials'])) {
            echo "\nTop Materials:\n";
            arsort($totalStats['materials']);
            $topMaterials = array_slice($totalStats['materials'], 0, 10, true);
            foreach ($topMaterials as $mat => $count) {
                echo "  {$mat}: " . number_format($count) . "\n";
            }
        }

        if (!empty($totalStats['brands'])) {
            echo "\nTop Brands:\n";
            arsort($totalStats['brands']);
            $topBrands = array_slice($totalStats['brands'], 0, 10, true);
            foreach ($topBrands as $brand => $count) {
                echo "  {$brand}: " . number_format($count) . "\n";
            }
        }

        if (!empty($totalStats['custom_tags'])) {
            echo "\nCustom Tags:\n";
            arsort($totalStats['custom_tags']);
            foreach ($totalStats['custom_tags'] as $custom => $count) {
                echo "  {$custom}: " . number_format($count) . "\n";
            }
        }

        echo "\nUnique Counts:\n";
        echo "  Categories used: " . count($totalStats['categories']) . "\n";
        echo "  Unique objects: " . count($totalStats['objects']) . "\n";
        echo "  Unique materials: " . count($totalStats['materials']) . "\n";
        echo "  Unique brands: " . count($totalStats['brands']) . "\n";
        echo "  Unique custom tags: " . count($totalStats['custom_tags']) . "\n";

        return $totalStats;
    }
}
