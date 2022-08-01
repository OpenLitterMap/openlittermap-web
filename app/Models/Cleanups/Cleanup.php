<?php

namespace App\Models\Cleanups;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cleanup extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * Relationship
     */
    public function cleanups ()
    {
        return $this->belongsToMany(Cleanup::class);
    }
}
