<?php

namespace App\Models\Cleanups;

use App\Models\User\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cleanup extends Model
{
    use HasFactory;

    protected $guarded = [];

    public $appends = [
        'timeDiff',
        'startsAt'
    ];

    /**
     * A Cleanup can have many Users
     */
    public function users ()
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * eg: 8 months from now
     */
    public function getTimeDiffAttribute ()
    {
        return Carbon::parse($this->date)->diffForHumans();
    }

    /**
     * 20th April 2023
     */
    public function getStartsAtAttribute ()
    {
        return Carbon::parse($this->date)->format('d F Y');
    }
}
