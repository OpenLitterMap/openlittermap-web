<?php

namespace App\Models;

use App\Models\AI\Annotation;
use App\Models\Litter\Categories\Brand;
use App\Models\Teams\Team;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property Collection $customTags
 * @property User $user
 *
 * @method Builder onlyFromUsersThatAllowTagging
 */
class Photo extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $appends = ['selected', 'picked_up'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['datetime'];
    }

    /**
     * Create an Accessor that adds ['selected' => false] to each record
     */
    public function getSelectedAttribute(): bool
    {
        return false;
    }

    /**
     * Wrapper around photo presence, for better readability
     */
    public function getPickedUpAttribute(): bool
    {
        return ! $this->remaining;
    }

    /**
     * A photo can have many bounding boxes associated with it
     */
    public function boxes(): HasMany
    {
        return $this->hasMany(Annotation::class);
    }

    /**
     * All Categories
     *
     * @return array<string>
     */
    public static function categories(): array
    {
        return [
            'smoking',
            'food',
            'coffee',
            'alcohol',
            'softdrinks',
            'sanitary',
            'coastal',
            'dumping',
            'industrial',
            'brands',
            'dogshit',
            'art',
            'material',
            'other',
        ];
    }

    /**
     * All Currently available Brands
     */
    public static function getBrands(): array
    {
        return Brand::types();
    }

    /**
     * User who uploaded the photo
     *
     * This is unnecessarily loading
     * - photos_count
     * - team
     * - total_categories
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Team that uploaded the photo
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    /**
     * Return the tags for an image
     *
     * Remove any keys with null values
     */
    public function tags(): array
    {
        $tags = [];
        foreach ($this->categories() as $category) {
            if ($this->$category) {
                foreach ($this->$category->types() as $tag) {
                    if (is_null($this->$category[$tag])) {
                        unset($this->$category[$tag]);
                    } else {
                        $tags[$category][$tag] = $this->$category[$tag];
                    }
                }
            }
        }

        return $tags;
    }

    /**
     * Update and return the total amount of litter in a photo
     */
    public function total(): void
    {
        $total = 0;

        foreach ($this->categories() as $category) {
            if ($this->$category) {
                // We dont want to include brands in total_litter
                // Increment total_litter when its not brands
                if ($category !== 'brands') {
                    $total += $this->$category->total();
                }
            }
        }

        $this->total_litter = $total;
        $this->save();
    }

    /**
     * Save translation key => value for every item on each category that has a value
     *
     * Format: category.item quantity, category.item quantity,
     *
     * eg: "smoking.butts 3, alcohol.beerBottles 4,"
     */
    public function translate(): void
    {
        $result_string = '';

        foreach ($this->categories() as $category) {
            if ($this->$category) {
                $result_string .= $this->$category->translate();
            }
        }

        $this->result_string = $result_string;
        $this->save();
    }

    /**
     * Location relationships
     */
    public function country(): HasOne
    {
        return $this->hasOne('App\Models\Location\Country');
    }

    public function state(): HasOne
    {
        return $this->hasOne('App\Models\Location\State');
    }

    public function city(): HasOne
    {
        return $this->hasOne('App\Models\Location\City');
    }

    public function adminVerificationLog(): HasOne
    {
        // Use hasOne or hasMany depending on your needs
        return $this->hasOne(AdminVerificationLog::class, 'photo_id');
    }

    /**
     * Litter categories
     */
    public function smoking(): BelongsTo
    {
        return $this->belongsTo('App\Models\Litter\Categories\Smoking', 'smoking_id', 'id');
    }

    public function food(): BelongsTo
    {
        return $this->belongsTo('App\Models\Litter\Categories\Food', 'food_id', 'id');
    }

    public function coffee(): BelongsTo
    {
        return $this->belongsTo('App\Models\Litter\Categories\Coffee', 'coffee_id', 'id');
    }

    public function softdrinks(): BelongsTo
    {
        return $this->belongsTo('App\Models\Litter\Categories\SoftDrinks', 'softdrinks_id', 'id');
    }

    public function alcohol(): BelongsTo
    {
        return $this->belongsTo('App\Models\Litter\Categories\Alcohol', 'alcohol_id', 'id');
    }

    public function sanitary(): BelongsTo
    {
        return $this->belongsTo('App\Models\Litter\Categories\Sanitary', 'sanitary_id', 'id');
    }

    public function dumping(): BelongsTo
    {
        return $this->belongsTo('App\Models\Litter\Categories\Dumping', 'dumping_id', 'id');
    }

    public function other(): BelongsTo
    {
        return $this->belongsTo('App\Models\Litter\Categories\Other', 'other_id', 'id');
    }

    public function industrial(): BelongsTo
    {
        return $this->belongsTo('App\Models\Litter\Categories\Industrial', 'industrial_id', 'id');
    }

    public function coastal(): BelongsTo
    {
        return $this->belongsTo('App\Models\Litter\Categories\Coastal', 'coastal_id', 'id');
    }

    public function art(): BelongsTo
    {
        return $this->belongsTo('App\Models\Litter\Categories\Art', 'art_id', 'id');
    }

    public function brands(): BelongsTo
    {
        return $this->belongsTo('App\Models\Litter\Categories\Brand', 'brands_id', 'id');
    }

    public function trashdog(): BelongsTo
    {
        return $this->belongsTo('App\Models\Litter\Categories\TrashDog', 'trashdog_id', 'id');
    }

    public function dogshit(): BelongsTo
    {
        return $this->belongsTo('App\Models\Litter\Categories\Dogshit', 'dogshit_id', 'id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo('App\Models\Litter\Categories\Material', 'material_id', 'id');
    }

    // public function politics() {
    //     return $this->belongsTo('App\Models\Litter\Categories\Politicals', 'political_id', 'id');
    // }

    public function customTags(): HasMany
    {
        return $this->hasMany(CustomTag::class);
    }

    public function scopeOnlyFromUsersThatAllowTagging(Builder $query): void
    {
        $query->whereNotIn('user_id', function ($q): void {
            $q->select('id')
                ->from('users')
                ->where('prevent_others_tagging_my_photos', true);
        });
    }
}
