<?php

namespace App\Models\Litter\Tags;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class LitterObject extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';

    protected $guarded = [];

    protected $hidden = ['pivot'];

    protected function casts(): array
    {
        return ['retired_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'key';
    }

    /**
     * Objects still offered to users. Retirement is an explicit fact, not an accident of a
     * missing pivot row — the retirement process creates pivots deliberately.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('retired_at');
    }

    public function isRetired(): bool
    {
        return $this->retired_at !== null;
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
