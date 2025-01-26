<?php

namespace App\Models\Litter\Tags;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class LitterModel extends Model
{
    protected $table = 'litter_models';

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

    // Nullable
    public function tagType(): BelongsTo
    {
        return $this->belongsTo(TagType::class);
    }

    // The new many-to-many pivot for "contextual" materials
    public function contextualMaterials(): BelongsToMany
    {
        return $this->belongsToMany(Materials::class, 'litter_model_materials', 'litter_model_id', 'material_id');
    }
}
