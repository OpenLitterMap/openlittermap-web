<?php

namespace App\Models\Litter\Tags;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LitterModel extends Model
{
    protected $table = 'category_litter_object_tag_type';

    protected $primaryKey = null;

    public $incrementing = false;

    protected $guarded = [];

    protected $hidden = ['created_at', 'updated_at'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function litterObject(): BelongsTo
    {
        return $this->belongsTo(LitterObject::class);
    }

    public function tagType(): BelongsTo
    {
        return $this->belongsTo(TagType::class);
    }
}
