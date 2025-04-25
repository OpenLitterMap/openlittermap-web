<?php

namespace App\Models\Achievements;

use App\Models\Users\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Achievement extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'meta' => 'array',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_achievements')
            ->withPivot(['unlocked_at','progress','target','snapshot'])
            ->withTimestamps();
    }
}
