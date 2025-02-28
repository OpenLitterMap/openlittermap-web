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
            ->using(CategoryLitterObject::class)
            ->withPivot('id', 'litter_object_id', 'category_id');
    }

    // this is wrong.
//    public function materials(): BelongsToMany
//    {
//        return $this->belongsToMany(
//            Materials::class,
//            'category_litter_object_material',
//            'category_litter_object_id',
//            'material_id'
//        );
//    }
}
