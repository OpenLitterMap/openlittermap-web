<?php

namespace App\Models;

use App\Models\AI\Annotation;
use App\Models\Teams\Team;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * @property Collection $customTags
 * @property Collection $tags
 * @property array $compiled_tags
 */
class Photo extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $appends = ['selected', 'picked_up'];

    protected $casts = ['datetime'];

    /**
     * Create an Accessor that adds ['selected' => false] to each record
     */
    public function getSelectedAttribute ()
    {
        return false;
    }

    /**
     * Wrapper around photo presence, for better readability
     */
    public function getPickedUpAttribute ()
    {
        return !$this->remaining;
    }

    /**
     * A photo can have many bounding boxes associated with it
     */
    public function boxes ()
    {
        return $this->hasMany(Annotation::class);
    }

    /**
     * User who uploaded the photo
     *
     * This is unnecessarily loading
     * - photos_count
     * - team
     * - total_categories
     */
    public function user ()
    {
    	return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Team that uploaded the photo
     */
    public function team ()
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)
            ->using(PhotoTag::class)
            ->withPivot(['quantity'])
            ->withTimestamps();
    }

    /**
     * Location relationships
     */
    public function country ()
    {
    	return $this->hasOne('App\Models\Location\Country');
    }

    public function state ()
    {
        return $this->hasOne('App\Models\Location\State');
    }

    public function city ()
    {
    	return $this->hasOne('App\Models\Location\City');
    }

    public function customTags(): HasMany
    {
        return $this->hasMany(CustomTag::class);
    }

    /**
     * Save translation key => value for every item on each category that has a value
     *
     * Format: category.item quantity, category.item quantity,
     *
     * eg. smoking.butts 3, alcohol.beerBottles 4,
     *
     * We use the result_string on the global map for 2 reasons.
     * 1. We don't have to eager load any data.
     * 2. This format can be translated into any language.
     */
    public function translate ()
    {
        $this->load('tags.category');

        $result = [];
        foreach ($this->tags as $tag) {
            $result[] = $tag->category->slug . '.' . $tag->slug . ' ' . $tag->pivot->quantity;
        }

        $this->result_string = implode(', ', $result);
        $this->save();
    }

    public function getCompiledTagsAttribute(): array
    {
        $this->load('tags.category');

        return $this->tags
            ->groupBy('category.slug')
            ->map(function ($tags) {
                return $tags
                    ->keyBy('slug')
                    ->map(function ($tag) {
                        return $tag->pivot->quantity;
                    })
                    ->toArray();
            })
            ->toArray();
    }

    public function scopeInCategories(Builder $query, array $categories): Builder
    {
        return $query->whereHas('tags.category', function ($q) use ($categories) {
            return $q->whereIn(DB::raw('categories.slug'), $categories);
        });
    }
}
