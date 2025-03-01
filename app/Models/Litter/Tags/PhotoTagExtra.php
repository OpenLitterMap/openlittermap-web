<?php

namespace App\Models\Litter\Tags;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PhotoTagExtra extends Model
{
    use HasFactory;

    protected $table = 'photo_tag_extras';

    protected $guarded = [];

    public function extra(): MorphTo
    {
        return $this->morphTo(null, 'extra_type_id', 'extra_id');
    }
}
