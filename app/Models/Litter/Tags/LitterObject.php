<?php

namespace App\Models\Litter\Tags;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LitterObject extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $hidden = ['pivot'];

    public function getRouteKeyName(): string
    {
        return 'key';
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'category_litter_object')
            ->using(CategoryObject::class)
            ->withPivot('id', 'litter_object_id', 'category_id');
    }

    /**
     * We need to call the function materials() to get the materials
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
