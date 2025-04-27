<?php

namespace App\Models\Teams;

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
    	return $this->belongsToMany('App\Models\Users\User');
    }

    public function leader ()
    {
    	return $this->belongsTo('App\Models\Users\User', 'leader');
    }

    // double check this
    public function photos ()
    {
        return $this->hasManyThrough('App\Models\Users\User', 'App\Models\Photo');
    }

}
