<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MerchantPhoto extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $table = 'merchant_photos';

    /**
     * Relationships
     */
    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }
}
