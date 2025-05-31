<?php

namespace App\Models\Achievements;

use App\Models\Users\User;
use App\Services\Redis\RedisMetricsCollector;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Achievement extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'threshold' => 'integer',
        'xp' => 'integer',
        'tag_id' => 'integer',
    ];

    /**
     * Scope to get achievements not yet unlocked by a user
     */
    public function scopeNotUnlockedBy($query, User $user)
    {
        return $query->whereDoesntHave('users', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        });
    }

    /**
     * Users who have unlocked this achievement
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_achievements')
            ->withPivot(['unlocked_at'])
            ->withTimestamps();
    }

    /**
     * Check if a user has unlocked this achievement
     */
    public function isUnlockedBy(User $user): bool
    {
        return $this->users()
            ->where('user_id', $user->id)
            ->exists();
    }

    /**
     * Get the progress for a user towards this achievement
     */
    public function getProgressFor(User $user): int
    {
        $counts = RedisMetricsCollector::getUserCounts($user->id);

        // Handle dimension-wide achievements
        if (!$this->tag_id) {
            return match($this->type) {
                'uploads' => $counts['uploads'] ?? 0,
                'objects' => array_sum($counts['objects'] ?? []),
                'categories' => count($counts['categories'] ?? []),
                'materials' => array_sum($counts['materials'] ?? []),
                'brands' => array_sum($counts['brands'] ?? []),
                default => 0,
            };
        }

        // Handle per-tag achievements
        // First, we need to find the tag key for this tag_id
        $tagKey = $this->getTagKeyForId($this->tag_id);
        if (!$tagKey) {
            return 0;
        }

        // Get the count from the appropriate bucket
        $bucket = match($this->type) {
            'object' => $counts['objects'] ?? [],
            'category' => $counts['categories'] ?? [],
            'material' => $counts['materials'] ?? [],
            'brand' => $counts['brands'] ?? [],
            'customTag' => $counts['custom_tags'] ?? [],
            default => [],
        };

        return (int) ($bucket[$tagKey] ?? 0);
    }

    /**
     * Get the tag key for a given tag ID
     */
    private function getTagKeyForId(int $tagId): ?string
    {
        // Cache this lookup
        return Cache::remember("tag_key:{$this->type}:{$tagId}", 3600, function () use ($tagId) {
            $table = match($this->type) {
                'object' => 'litter_objects',
                'category' => 'categories',
                'material' => 'materials',
                'brand' => 'brandslist',
                'customTag' => 'custom_tags',
                default => null,
            };

            if (!$table) {
                return null;
            }

            return \DB::table($table)->where('id', $tagId)->value('key');
        });
    }

    /**
     * Get progress percentage towards this achievement
     */
    public function getProgressPercentageFor(User $user): float
    {
        $current = $this->getProgressFor($user);

        if ($current >= $this->threshold) {
            return 100.0;
        }

        return round(($current / $this->threshold) * 100, 1);
    }

    /**
     * Get remaining count needed to unlock this achievement
     */
    public function getRemainingFor(User $user): int
    {
        $current = $this->getProgressFor($user);
        $remaining = $this->threshold - $current;

        return max(0, $remaining);
    }
}
