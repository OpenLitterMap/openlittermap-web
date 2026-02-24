<?php

namespace App\Models\Teams;

use App\Models\Teams\TeamType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
    	'images_remaining',
    	'total_images',
    	'total_litter',
    	'leader',
        'created_by',
        'identifier',
        'leaderboards',
        'is_trusted',
        'safeguarding',
        'school_roll_number',
        'contact_email',
        'academic_year',
        'class_group',
        'county',
    ];

    protected $casts = [
        'safeguarding' => 'boolean',
        'is_trusted' => 'boolean',
        'leaderboards' => 'boolean',
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

    // double check this
    public function photos ()
    {
        return $this->hasManyThrough('App\Models\Users\User', 'App\Models\Photo');
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
