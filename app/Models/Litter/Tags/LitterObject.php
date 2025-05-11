<?php

namespace App\Models\Litter\Tags;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class LitterObject extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';

    protected $guarded = [];

    protected $hidden = ['pivot'];

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
