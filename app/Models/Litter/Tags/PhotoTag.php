<?php

namespace App\Models\Litter\Tags;

use App\Models\Photo;
use App\Models\Litter\Categories\Brand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PhotoTag extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function photo(): BelongsTo
    {
        return $this->belongsTo(Photo::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function object(): BelongsTo
    {
        return $this->belongsTo(LitterObject::class, 'object_id');
    }

    public function extraTags(): HasMany
    {
        return $this->hasMany(PhotoTagExtraTags::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function attachExtraTags (array $extras, string $type, int $index): void
    {
        foreach ($extras as $tag) {
            PhotoTagExtraTags::firstOrCreate([
                'photo_tag_id' => $this->id,
                'tag_type' => $type,
                'tag_type_id' => $tag['id'],
                'index' => $index,
            ], [
                'quantity' => $tag['quantity'],
            ]);
        }
    }
}
