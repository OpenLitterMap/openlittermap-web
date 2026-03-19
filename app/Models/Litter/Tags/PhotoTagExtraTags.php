<?php

namespace App\Models\Litter\Tags;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PhotoTagExtraTags extends Model
{
    use HasFactory;

    protected $table = 'photo_tag_extra_tags';

    protected $guarded = [];

    public function extraTag(): MorphTo
    {
        return $this->morphTo(null, 'tag_type', 'tag_type_id');
    }
}
