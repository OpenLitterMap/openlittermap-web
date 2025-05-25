<?php

namespace App\Models\Achievements;

use App\Models\Users\User;
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
     * Users who have unlocked this achievement
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_achievements')
            ->withPivot(['unlocked_at'])
            ->withTimestamps();
    }

    /**
     * Get the display name for this achievement
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->metadata['name'] ?? $this->generateDefaultName();
    }

    /**
     * Get the description for this achievement
     */
    public function getDescriptionAttribute(): string
    {
        return $this->metadata['description'] ?? $this->generateDefaultDescription();
    }

    /**
     * Get the icon for this achievement
     */
    public function getIconAttribute(): string
    {
        return $this->metadata['icon'] ?? '🏆';
    }

    /**
     * Generate a default name if not in metadata
     */
    private function generateDefaultName(): string
    {
        $name = ucfirst($this->type);

        if ($this->tag_id && isset($this->metadata['tag_name'])) {
            $name .= ': ' . $this->metadata['tag_name'];
        }

        $name .= ' x' . number_format($this->threshold);

        return $name;
    }

    /**
     * Generate a default description if not in metadata
     */
    private function generateDefaultDescription(): string
    {
        $action = match($this->type) {
            'uploads' => 'Upload',
            'objects' => 'Tag',
            'categories' => 'Use',
            'materials' => 'Tag',
            'brands' => 'Tag',
            default => 'Reach',
        };

        $target = match($this->type) {
            'uploads' => 'photos',
            'objects' => 'objects',
            'categories' => 'categories',
            'materials' => 'materials',
            'brands' => 'brands',
            default => 'items',
        };

        return "{$action} {$this->threshold} {$target}";
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
        // This would need to query Redis for the current count
        // Implementation depends on your RedisMetricsCollector
        return 0;
    }
}
