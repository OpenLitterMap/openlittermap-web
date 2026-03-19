<?php

namespace App\Models\Achievements;

use App\Models\Users\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property-read string   $type
 * @property-read int      $threshold
 * @property-read int|null $tag_id
 * @property-read int      $xp
 * @property-read array    $metadata
 */
class Achievement extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'metadata'  => 'array',
        'threshold' => 'integer',
        'tag_id'    => 'integer',
    ];

    /** Users that have already unlocked this achievement. */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_achievements')
            ->withPivot('unlocked_at')
            ->withTimestamps();
    }
}
