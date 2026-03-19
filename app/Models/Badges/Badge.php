<?php

namespace App\Models\Badges;

use App\Models\Users\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;

class Badge extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $appends = ['full_path'];

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_badges')->withTimestamps()->withPivot('awarded_at');
    }

    public function getFullPathAttribute(): string
    {
        return Storage::disk('public')->url($this->filename);
    }
}
