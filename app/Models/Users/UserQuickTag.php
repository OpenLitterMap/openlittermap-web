<?php

namespace App\Models\Users;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserQuickTag extends Model
{
    protected $fillable = [
        'user_id',
        'clo_id',
        'type_id',
        'quantity',
        'picked_up',
        'materials',
        'brands',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'clo_id' => 'integer',
            'type_id' => 'integer',
            'quantity' => 'integer',
            'picked_up' => 'boolean',
            'materials' => 'array',
            'brands' => 'array',
            'sort_order' => 'integer',
        ];
    }

    protected $hidden = ['user_id', 'created_at', 'updated_at'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
