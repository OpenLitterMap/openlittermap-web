<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TagType extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function getRouteKeyName(): string
    {
        return 'key';
    }

    public function litterObjects (): BelongsToMany
    {
        return $this->belongsToMany(LitterObject::class, 'litter_object_tag_type')->withTimestamps();
    }

    public function materials(): BelongsToMany
    {
        return $this->belongsToMany(
            Materials::class,
            'tag_type_material',
            'tag_type_id',
            'material_id'
        )->withTimestamps();
    }
}
