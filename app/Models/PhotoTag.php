<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Litter\Categories\Brand;
use App\Models\Litter\Categories\Material;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PhotoTag extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function photo (): BelongsTo
    {
        return $this->belongsTo(Photo::class);
    }

    public function category (): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function object (): BelongsTo
    {
        return $this->belongsTo(LitterObject::class, 'object_id');
    }

    public function brand (): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function materials (): BelongsToMany
    {
        return $this->belongsToMany(Material::class, 'material_photo_tag');
    }
}
