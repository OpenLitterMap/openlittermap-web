<?php

namespace App\Models\Litter\Tags;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class LitterObject extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';

    protected $guarded = [];

    protected $hidden = ['pivot'];

    protected $casts = ['retired_at' => 'datetime', 'merged_into_id' => 'integer', 'merged_into_type_id' => 'integer'];

    public function scopeRetired(Builder $query, bool $retired = true): Builder
    {
        $column = $this->qualifyColumn('retired_at');

        return $retired ? $query->whereNotNull($column) : $query->whereNull($column);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->retired(false);
    }

    public function getRouteKeyName(): string
    {
        return 'key';
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            Category::class,
            'category_litter_object',
            'litter_object_id',
            'category_id',
        )
        ->using(CategoryObject::class)
        ->withPivot('id')
        ->withTimestamps();
    }

    /**
     * Do we need to call the function materials() to get the materials?
     *
     * @return mixed
     */
    public function materials()
    {
        return $this->categories->flatMap(function ($category) {
            return $category->pivot->materials()->get();
        });
    }
}
