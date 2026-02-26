<?php

namespace App\Models\Litter\Tags;

use App\Models\Photo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;

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
        return $this->belongsTo(LitterObject::class, 'litter_object_id');
    }

    public function categoryObject(): BelongsTo
    {
        return $this->belongsTo(CategoryObject::class, 'category_litter_object_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(LitterObjectType::class, 'litter_object_type_id');
    }

    public function extraTags(): HasMany
    {
        return $this->hasMany(PhotoTagExtraTags::class);
    }

    public function attachExtraTags(array $extras, string $type): void
    {
        if (empty($extras)) {
            return;
        }

        // Materials and custom_tags are set membership — always qty=1
        $forceQtyOne = in_array($type, ['material', 'custom_tag']);

        $rows = [];

        foreach ($extras as $tag) {
            if (empty($tag['id'])) {
                Log::warning("Skipping extra tag with missing ID for type {$type}");
                continue;
            }

            $rows[] = [
                'photo_tag_id' => $this->id,
                'tag_type'     => $type,
                'tag_type_id'  => $tag['id'],
                'quantity'     => $forceQtyOne ? 1 : ($tag['quantity'] ?? 1),
                'created_at'   => now(),
                'updated_at'   => now(),
            ];
        }

        if (!empty($rows)) {
            PhotoTagExtraTags::upsert(
                $rows,
                ['photo_tag_id', 'tag_type', 'tag_type_id'],
                ['quantity', 'updated_at']
            );
        }
    }
}
