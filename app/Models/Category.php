<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property Collection<Tag> $tags
 */
class Category extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }
}
