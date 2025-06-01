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

    /** Allow mass-assignment on all columns (seeders use ->create()). */
    protected $guarded = [];

    /** Native casts. */
    protected $casts = [
        'metadata'  => 'array',
        'threshold' => 'integer',
        'xp'        => 'integer',
        'tag_id'    => 'integer',
    ];

    /* ------------------------------------------------------------------ */
    /*  Relationships                                                     */
    /* ------------------------------------------------------------------ */

    /** Users that have already unlocked this achievement. */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_achievements')
            ->withPivot('unlocked_at')
            ->withTimestamps();
    }

    /* ------------------------------------------------------------------ */
    /*  Query scopes                                                      */
    /* ------------------------------------------------------------------ */

    /** Achievements not yet unlocked by the given user. */
    public function scopeNotUnlockedBy($query, User $user)
    {
        return $query->whereDoesntHave('users', fn ($q) => $q->where('user_id', $user->id));
    }

    /* ------------------------------------------------------------------ */
    /*  Convenience helpers                                               */
    /* ------------------------------------------------------------------ */

    /** Quick boolean (cached in the pivot relationship). */
    public function isUnlockedBy(User $user): bool
    {
        return $this->users()->where('user_id', $user->id)->exists();
    }
}
