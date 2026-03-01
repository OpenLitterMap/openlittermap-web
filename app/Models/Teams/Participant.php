<?php

namespace App\Models\Teams;

use App\Models\Photo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Participant extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'slot_number',
        'display_name',
        'session_token',
        'is_active',
        'last_active_at',
    ];

    protected $hidden = ['session_token'];

    protected $casts = [
        'is_active' => 'boolean',
        'last_active_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }

    public static function generateToken(): string
    {
        return Str::random(64);
    }

    public function resetToken(): string
    {
        $this->session_token = static::generateToken();
        $this->save();

        return $this->session_token;
    }

    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }

    public function activate(): void
    {
        $this->update(['is_active' => true]);
    }
}
