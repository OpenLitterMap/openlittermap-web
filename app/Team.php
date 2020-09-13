<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    protected $fillable = [
    	'name',
        'type_id',
        'type_name',
    	'members',
    	'images_remaining',
    	'total_images',
    	'total_litter',
    	'leader'
    ];

    /**
     * Relationships
     */
    public function users() {
    	return $this->belongsToMany('App\Models\User\User');
    }

    public function leader() {
    	return $this->hasOne('App\Models\User\User');
    }

    // double check this
    public function photos() {
        return $this->hasManyThrough('App\Models\User\User', 'App\Models\Photo');
    }

}
