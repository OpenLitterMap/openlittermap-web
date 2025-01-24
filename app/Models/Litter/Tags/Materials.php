<?php

namespace App\Models\Litter\Tags;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Materials extends Model
{
    use HasFactory;

    protected $guarded = [];

    public $table = 'materials';

    public $timestamps = false;

    public function photoTags(): BelongsToMany
    {
        return $this->belongsToMany(PhotoTag::class, 'material_photo_tag');
    }

    public function litterObjects(): BelongsToMany
    {
        return $this->belongsToMany(LitterObject::class, 'litter_object_material');
    }

    public function tagTypes(): BelongsToMany
    {
        return $this->belongsToMany(TagType::class, 'tag_type_material');
    }
}
