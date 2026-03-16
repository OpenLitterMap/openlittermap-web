<?php

namespace App\Models\Teams;

use App\Models\Photo;
use App\Models\Teams\TeamType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    use HasFactory;

    protected $appends = ['type_name'];

    protected $attributes = [
        'members' => 1,
    ];

    protected $fillable = [
    	'name',
        'type_id',
        'type_name',
    	'members',
    	'leader',
        'created_by',
        'identifier',
        'leaderboards',
        'is_trusted',
        'safeguarding',
        'contact_email',
        'academic_year',
        'class_group',
        'county',
        'logo',
        'max_participants',
        'participant_sessions_enabled',
    ];

    protected $casts = [
        'safeguarding' => 'boolean',
        'is_trusted' => 'boolean',
        'leaderboards' => 'boolean',
        'participant_sessions_enabled' => 'boolean',
    ];

    /**
     * Relationships
     */
    public function users ()
    {
    	return $this->belongsToMany('App\Models\Users\User');
    }

    public function leader ()
    {
    	return $this->belongsTo('App\Models\Users\User', 'leader');
    }

    public function teamType()
    {
        return $this->belongsTo(TeamType::class, 'type_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class, 'team_id');
    }

    /**
     * Add total_photos and total_tags as correlated subqueries.
     */
    public function scopeWithPhotoStats(Builder $query): void
    {
        $query->addSelect([
            'total_photos' => Photo::query()
                ->selectRaw('COUNT(*)')
                ->whereColumn('photos.team_id', 'teams.id')
                ->where('photos.is_public', true),
            'total_tags' => Photo::query()
                ->selectRaw('COALESCE(SUM(total_tags), 0)')
                ->whereColumn('photos.team_id', 'teams.id')
                ->where('photos.is_public', true),
        ]);
    }

    public function isSchool(): bool
    {
        return $this->type_name === 'school';
    }

    public function isLeader(int $userId): bool
    {
        return $this->leader === $userId;
    }

    /**
     * Does this team have safeguarding mode enabled?
     */
    public function hasSafeguarding(): bool
    {
        return (bool) $this->safeguarding;
    }

    public function participants(): HasMany
    {
        return $this->hasMany(Participant::class);
    }

    public function activeParticipants(): HasMany
    {
        return $this->hasMany(Participant::class)->where('is_active', true);
    }

    public function hasParticipantSessions(): bool
    {
        return $this->participant_sessions_enabled && $this->isSchool();
    }

    // ─── Accessors ───

    /**
     * Get the human-readable team type name.
     */
    public function getTypeNameAttribute(): string
    {
        // If type_name column has a value, use it directly
        if (! empty($this->attributes['type_name'])) {
            return $this->attributes['type_name'];
        }

        // Look up from the teamType relation
        if ($this->type_id) {
            return $this->teamType?->team ?? 'community';
        }

        return 'community';
    }
}
