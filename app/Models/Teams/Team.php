<?php

namespace App\Models\Teams;

use App\Models\User\User;
use App\Models\Photo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    use HasFactory;

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
        'is_trusted'
    ];

    protected $casts = [
        'is_trusted' => 'boolean'
    ];

    /**
     * Relationships
     */
    public function users ()
    {
    	return $this->belongsToMany(User::class);
    }

    public function leader ()
    {
    	return $this->belongsTo(User::class, 'leader');
    }

    // double check this
    public function photos ()
    {
        return $this->hasManyThrough(User::class, Photo::class);
    }

}
