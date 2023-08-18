<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Merchant extends Model
{
    use HasFactory;

    protected $table = 'merchants';

    protected $guarded = [];

    /**
     * Relationships
     */
    public function photos()
    {
        return $this->hasMany(MerchantPhoto::class);
    }
}
