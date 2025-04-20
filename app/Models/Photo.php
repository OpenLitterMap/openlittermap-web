<?php

namespace App\Models;

use App\Models\AI\Annotation;
use App\Models\Litter\Categories\Brand;
use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\CustomTagNew;
use App\Models\Litter\Tags\Materials;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Teams\Team;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property Collection $customTags
 * @property User $user
 * @method Builder onlyFromUsersThatAllowTagging
 */
class Photo extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $appends = ['selected', 'picked_up'];

    protected $casts = [
        'datetime' => 'datetime',
        'summary' => 'array',

        // deprecated
        // 'tags' => 'array',
        // 'customTags' => 'array',
    ];

    public function photoTags (): HasMany
    {
        return $this->hasMany(PhotoTag::class);
    }

    /**
     * An Accessor that adds ['selected' => false] to each record
     */
    public function getSelectedAttribute (): bool
    {
        return false;
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
    public function user (): BelongsTo
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

    public function scopeOnlyFromUsersThatAllowTagging(Builder $query)
    {
        $query->whereNotIn('user_id', function ($q) {
            $q->select('id')
                ->from('users')
                ->where('prevent_others_tagging_my_photos', true);
        });
    }

    public function createTag (array $data): PhotoTag
    {
        return $this->photoTags()->create($data);
    }

    public function calculateTotalTags (): int
    {
        $baseTags = $this->photoTags()->sum('quantity');

        $extraTags = $this->photoTags()
            ->with('extraTags')
            ->get()
            ->flatMap(fn($tag) => $tag->extraTags)
            ->sum('quantity');

        $this->total_tags = $baseTags + $extraTags;
        $this->save();

        return $this->total_tags;
    }

    /**
     * Build and persist a single‑query JSON summary of this photo's tags + aggregates,
     * grouped first by category key, then by object key (with extra-tags nested),
     * plus flat totals for tags, objects, materials, brands, and custom tags.
     *
     * Final summary format:
     * [
     *   'tags' => [
     *     '<categoryKey>' => [
     *       '<objectKey>' => [
     *         'quantity'    => (int),
     *         'materials'   => [ '<materialKey>' => (int), ... ],
     *         'brands'      => [ '<brandKey>'    => (int), ... ],
     *         'custom_tags' => [ '<customKey>'   => (int), ... ],
     *       ],
     *       ... // more objects per category
     *     ],
     *     ... // more categories
     *   ],
     *   'totals' => [
     *     'total_tags'    => (int),
     *     'total_objects' => (int),
     *     'by_category'   => [ '<categoryKey>' => (int), ... ],
     *     'materials'     => (int),
     *     'brands'        => (int),
     *     'custom_tags'   => (int),
     *   ],
     * ]
     *
     * Categories and objects are ordered descending by their quantities.
     *
     * @return $this
     */
    public function generateSummary(): self
    {
        // 1) Eager‑load all PhotoTags with related category, object, and extraTags
        $tags = $this->photoTags()
            ->with(['category', 'object', 'extraTags.extraTag'])
            ->get();

        $grouped         = [];
        $categoryTotals  = [];
        $totalTags       = 0;
        $totalObjects    = 0;
        $materialCount   = 0;
        $brandCount      = 0;
        $customTagCount  = 0;

        // 2) Walk through each tag record
        foreach ($tags as $pt) {
            // Use "custom" when there's no category (i.e. pure custom‑tag PhotoTags)
            $categoryKey = $pt->category?->key ?? 'custom';
            $objectKey   = $pt->object?->key ?? 'unknown';
            $qty         = $pt->quantity;

            // accumulate flat totals
            $totalTags += $qty;
            if (! is_null($pt->litter_object_id)) {
                $totalObjects += $qty;
            }

            // init per‑category/object slots
            $grouped[$categoryKey][$objectKey]['quantity'] =
                ($grouped[$categoryKey][$objectKey]['quantity'] ?? 0) + $qty;
            foreach (['materials', 'brands', 'custom_tags'] as $type) {
                $grouped[$categoryKey][$objectKey][$type] =
                    $grouped[$categoryKey][$objectKey][$type] ?? [];
            }

            // bump category total
            $categoryTotals[$categoryKey] =
                ($categoryTotals[$categoryKey] ?? 0) + $qty;

            // handle extra tags
            foreach ($pt->extraTags as $extra) {
                $extraQty = $extra->quantity;
                // accumulate flat and category totals
                $totalTags += $extraQty;
                $categoryTotals[$categoryKey] += $extraQty;

                // type-specific counters
                switch ($extra->tag_type) {
                    case 'material':
                        $materialCount += $extraQty;
                        $typeKey = 'materials';
                        break;
                    case 'brand':
                        $brandCount += $extraQty;
                        $typeKey = 'brands';
                        break;
                    case 'custom_tag':
                    default:
                        $customTagCount += $extraQty;
                        $typeKey = 'custom_tags';
                        break;
                }

                $tagKey = $extra->extraTag?->key;
                $grouped[$categoryKey][$objectKey][$typeKey][$tagKey] =
                    ($grouped[$categoryKey][$objectKey][$typeKey][$tagKey] ?? 0) + $extraQty;
            }
        }

        // 3) Sort categories and their objects by quantity desc
        foreach ($grouped as $catKey => &$objects) {
            uasort($objects, fn($a, $b) => $b['quantity'] <=> $a['quantity']);
        }
        unset($objects);
        uksort($grouped, fn($a, $b) =>
            ($categoryTotals[$b] ?? 0) <=> ($categoryTotals[$a] ?? 0)
        );

        // 4) Build flat totals array
        $totals = [
            'total_tags'    => $totalTags,
            'total_objects' => $totalObjects,
            'by_category'   => $categoryTotals,
            'materials'     => $materialCount,
            'brands'        => $brandCount,
            'custom_tags'   => $customTagCount,
        ];

        // 5) Persist summary JSON
        $summary = [
            'tags'   => $grouped,
            'totals' => $totals,
        ];

        $this->update(['summary' => $summary]);

        return $this;
    }

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

    public function adminVerificationLog()
    {
        // Use hasOne or hasMany depending on your needs
        return $this->hasOne(AdminVerificationLog::class, 'photo_id');
    }

    // ALL BELOW IS DEPRECATED

    /**
     * @deprecated
     * Wrapper around photo presence, for better readability
     */
    public function getPickedUpAttribute (): bool
    {
        return !$this->remaining;
    }

    /**
     * @deprecated
     * Update and return the total amount of litter in a photo
     */
    public function total ()
    {
        $total = 0;

        foreach ($this->categories() as $category)
        {
            if ($this->$category)
            {
                // We dont want to include brands in total_litter
                // Increment total_litter when its not brands
                if ($category !== 'brands')
                {
                    $total += $this->$category->total();
                }
            }
        }

        $this->total_litter = $total;
        $this->save();
    }

    /**
     * @deprecated
     * Save translation key => value for every item on each category that has a value
     *
     * Format: category.item quantity, category.item quantity,
     *
     * eg: "smoking.butts 3, alcohol.beerBottles 4,"
     */
    public function translate ()
    {
        $result_string = '';

        foreach ($this->categories() as $category)
        {
            if ($this->$category)
            {
                $result_string .= $this->$category->translate();
            }
        }

        $this->result_string = $result_string;
        $this->save();
    }


    /**
     * @deprecated
     * All Categories
     */
    public static function categories (): array
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
     * @deprecated
     * All Currently available Brands
     */
    public static function getBrands ()
    {
        return Brand::types();
    }

    /**
     * @deprecated
     * Return the tags for an image
     *
     * Remove any keys with null values
     */
    public function tags (): array
    {
        $tags = [];

        foreach ($this->categories() as $category)
        {
            if ($this->$category)
            {
                foreach ($this->$category->types() as $tag)
                {
                    if (is_null($this->$category[$tag]))
                    {
                        unset ($this->$category[$tag]);
                    }
                    else
                    {
                        $tags[$category][$tag] = $this->$category[$tag];
                    }
                }
            }
        }

        return $tags;
    }

    /**
     * @deprecated
     */
    public function smoking ()
    {
    	return $this->belongsTo('App\Models\Litter\Categories\Smoking', 'smoking_id', 'id');
    }

    /**
     * @deprecated
     */
    public function food ()
    {
    	return $this->belongsTo('App\Models\Litter\Categories\Food', 'food_id', 'id');
    }

    /**
     * @deprecated
     */
    public function coffee ()
    {
    	return $this->belongsTo('App\Models\Litter\Categories\Coffee', 'coffee_id', 'id');
    }

    /**
     * @deprecated
     */
    public function softdrinks ()
    {
    	return $this->belongsTo('App\Models\Litter\Categories\SoftDrinks', 'softdrinks_id', 'id');
	}

    /**
     * @deprecated
     */
	public function alcohol ()
    {
		return $this->belongsTo('App\Models\Litter\Categories\Alcohol', 'alcohol_id', 'id');
	}

    /**
     * @deprecated
     */
	public function sanitary ()
    {
		return $this->belongsTo('App\Models\Litter\Categories\Sanitary', 'sanitary_id', 'id');
	}

    /**
     * @deprecated
     */
    public function dumping ()
    {
        return $this->belongsTo('App\Models\Litter\Categories\Dumping', 'dumping_id', 'id');
    }

    /**
     * @deprecated
     */
	public function other ()
    {
		return $this->belongsTo('App\Models\Litter\Categories\Other', 'other_id', 'id');
	}

    /**
     * @deprecated
     */
    public function industrial ()
    {
        return $this->belongsTo('App\Models\Litter\Categories\Industrial', 'industrial_id', 'id');
    }

    /**
     * @deprecated
     */
    public function coastal ()
    {
        return $this->belongsTo('App\Models\Litter\Categories\Coastal', 'coastal_id', 'id');
    }

    /**
     * @deprecated
     */
    public function art ()
    {
        return $this->belongsTo('App\Models\Litter\Categories\Art', 'art_id', 'id');
    }

    /**
     * @deprecated
     */
    public function brands ()
    {
        return $this->belongsTo('App\Models\Litter\Categories\Brand', 'brands_id', 'id');
    }

    /**
     * @deprecated
     */
    public function trashdog ()
    {
        return $this->belongsTo('App\Models\Litter\Categories\TrashDog', 'trashdog_id', 'id');
    }

    /**
     * @deprecated
     */
    public function dogshit ()
    {
        return $this->belongsTo('App\Models\Litter\Categories\Dogshit', 'dogshit_id', 'id');
    }

    /**
     * @deprecated
     */
    public function material ()
    {
        return $this->belongsTo('App\Models\Litter\Categories\Material', 'material_id', 'id');
    }

    // public function politics() {
    //     return $this->belongsTo('App\Models\Litter\Categories\Politicals', 'political_id', 'id');
    // }

    /**
     * @deprecated
     */
    public function customTags(): HasMany
    {
        return $this->hasMany(CustomTag::class);
    }
}
