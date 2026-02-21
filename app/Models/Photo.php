<?php

namespace App\Models;

use App\Models\AI\Annotation;
use App\Models\Litter\Categories\Brand;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Teams\Team;
use App\Models\Users\User;
use App\Services\Tags\GeneratePhotoSummaryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property Collection $photoTags
 * @property User $user
 * @property array $summary
 * @property int $xp
 */
class Photo extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $appends = ['selected', 'picked_up'];

    protected $casts = [
        'datetime' => 'datetime',
        'summary' => 'array',
        'address_array' => 'array',
        'xp' => 'integer',
    ];

    // ─── Relationships ───

    public function photoTags(): HasMany
    {
        return $this->hasMany(PhotoTag::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    public function countryRelation(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function stateRelation(): BelongsTo
    {
        return $this->belongsTo(State::class, 'state_id');
    }

    public function cityRelation(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function boxes(): HasMany
    {
        return $this->hasMany(Annotation::class);
    }

    public function adminVerificationLog(): HasOne
    {
        return $this->hasOne(AdminVerificationLog::class, 'photo_id');
    }

    // ─── Scopes ───

    public function scopeOnlyFromUsersThatAllowTagging(Builder $query): void
    {
        $query->whereNotIn('user_id', function ($q) {
            $q->select('id')
                ->from('users')
                ->where('prevent_others_tagging_my_photos', true);
        });
    }

    // ─── Tags ───

    public function createTag(array $data): PhotoTag
    {
        return $this->photoTags()->create($data);
    }

    public function generateSummary(): self
    {
        app(GeneratePhotoSummaryService::class)->run($this);

        return $this;
    }

    public function calculateTotalTags(): int
    {
        $baseTags = $this->photoTags()->sum('quantity');

        $extraTags = $this->photoTags()
            ->with('extraTags')
            ->get()
            ->flatMap(fn ($tag) => $tag->extraTags)
            ->sum('quantity');

        $this->total_tags = $baseTags + $extraTags;
        $this->save();

        return $this->total_tags;
    }

    // ─── Accessors ───

    public function getSelectedAttribute(): bool
    {
        return false;
    }

    public function getPickedUpAttribute(): bool
    {
        return ! $this->remaining;
    }

    /**
     * Derive display_name from address_array (previously a dedicated column)
     */
    public function getDisplayNameAttribute(): ?string
    {
        $address = $this->address_array;

        if (! $address) {
            return null;
        }

        return implode(', ', array_values($address));
    }

    // ═══════════════════════════════════════════════════════════════════
    // DEPRECATED — needed for v5 tag migration, remove after
    // See PostMigrationCleanup.md
    // ═══════════════════════════════════════════════════════════════════

    /** @deprecated Remove after v5 migration */
    public function country()
    {
        return $this->hasOne('App\Models\Location\Country');
    }

    /** @deprecated Remove after v5 migration */
    public function state()
    {
        return $this->hasOne('App\Models\Location\State');
    }

    /** @deprecated Remove after v5 migration */
    public function city()
    {
        return $this->hasOne('App\Models\Location\City');
    }

    /** @deprecated Remove after v5 migration */
    public function total()
    {
        $total = 0;

        foreach ($this->categories() as $category) {
            if ($this->$category) {
                if ($category !== 'brands') {
                    $total += $this->$category->total();
                }
            }
        }

        $this->total_litter = $total;
        $this->save();
    }

    /** @deprecated Remove after v5 migration */
    public function translate()
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

    /** @deprecated Remove after v5 migration */
    public static function categories(): array
    {
        return [
            'smoking', 'food', 'coffee', 'alcohol', 'softdrinks',
            'sanitary', 'coastal', 'dumping', 'industrial', 'brands',
            'dogshit', 'art', 'material', 'other',
        ];
    }

    /** @deprecated Remove after v5 migration */
    public static function getBrands()
    {
        return Brand::types();
    }

    /** @deprecated Remove after v5 migration */
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

    /** @deprecated Remove after v5 migration */
    public function smoking()
    {
        return $this->belongsTo('App\Models\Litter\Categories\Smoking', 'smoking_id', 'id');
    }

    /** @deprecated Remove after v5 migration */
    public function food()
    {
        return $this->belongsTo('App\Models\Litter\Categories\Food', 'food_id', 'id');
    }

    /** @deprecated Remove after v5 migration */
    public function coffee()
    {
        return $this->belongsTo('App\Models\Litter\Categories\Coffee', 'coffee_id', 'id');
    }

    /** @deprecated Remove after v5 migration */
    public function softdrinks()
    {
        return $this->belongsTo('App\Models\Litter\Categories\SoftDrinks', 'softdrinks_id', 'id');
    }

    /** @deprecated Remove after v5 migration */
    public function alcohol()
    {
        return $this->belongsTo('App\Models\Litter\Categories\Alcohol', 'alcohol_id', 'id');
    }

    /** @deprecated Remove after v5 migration */
    public function sanitary()
    {
        return $this->belongsTo('App\Models\Litter\Categories\Sanitary', 'sanitary_id', 'id');
    }

    /** @deprecated Remove after v5 migration */
    public function dumping()
    {
        return $this->belongsTo('App\Models\Litter\Categories\Dumping', 'dumping_id', 'id');
    }

    /** @deprecated Remove after v5 migration */
    public function other()
    {
        return $this->belongsTo('App\Models\Litter\Categories\Other', 'other_id', 'id');
    }

    /** @deprecated Remove after v5 migration */
    public function industrial()
    {
        return $this->belongsTo('App\Models\Litter\Categories\Industrial', 'industrial_id', 'id');
    }

    /** @deprecated Remove after v5 migration */
    public function coastal()
    {
        return $this->belongsTo('App\Models\Litter\Categories\Coastal', 'coastal_id', 'id');
    }

    /** @deprecated Remove after v5 migration */
    public function art()
    {
        return $this->belongsTo('App\Models\Litter\Categories\Art', 'art_id', 'id');
    }

    /** @deprecated Remove after v5 migration */
    public function brands()
    {
        return $this->belongsTo('App\Models\Litter\Categories\Brand', 'brands_id', 'id');
    }

    /** @deprecated Remove after v5 migration */
    public function trashdog()
    {
        return $this->belongsTo('App\Models\Litter\Categories\TrashDog', 'trashdog_id', 'id');
    }

    /** @deprecated Remove after v5 migration */
    public function dogshit()
    {
        return $this->belongsTo('App\Models\Litter\Categories\Dogshit', 'dogshit_id', 'id');
    }

    /** @deprecated Remove after v5 migration */
    public function material()
    {
        return $this->belongsTo('App\Models\Litter\Categories\Material', 'material_id', 'id');
    }

    /** @deprecated Remove after v5 migration */
    public function customTags(): HasMany
    {
        return $this->hasMany(CustomTag::class);
    }
}
