<?php

namespace App\Models\Cleanups;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CleanupUser extends Model
{
    use HasFactory;

    /**
     * Pivot table for Cleanup, User
     */
    protected $table = 'cleanup_user';

    public function cleanup ()
    {
        return $this->belongsTo(Cleanup::class);
    }

    public function user ()
    {
        return $this->belongsTo(User::class);
    }
}
