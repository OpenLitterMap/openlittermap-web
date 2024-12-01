<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TagType extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function litterObjects (): BelongsToMany
    {
        return $this->belongsToMany(LitterObject::class, 'litter_object_tag_type')->withTimestamps();
    }
}
