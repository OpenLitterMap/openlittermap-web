<?php

namespace App\Models;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminVerificationLog extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'added_tags' => 'array',
        'removed_tags' => 'array'
    ];

    /**
     * The user who updated the tags
     */
    public function admin () {
        return $this->belongsTo(User::class, 'admin_id', 'id');
    }
}
