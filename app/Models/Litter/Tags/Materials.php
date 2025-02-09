<?php

namespace App\Models\Litter\Tags;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Materials extends Model
{
    use HasFactory;

    protected $guarded = [];

    public $table = 'materials';

    public $timestamps = false;

    protected $hidden = ['pivot'];

    public function categoryLitterObjects(): BelongsToMany
    {
        return $this->belongsToMany(
            CategoryLitterObject::class,
            'category_litter_object_material',
            'material_id',
            'category_litter_object_id'
        );
    }
}
