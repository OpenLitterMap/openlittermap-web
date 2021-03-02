<?php

namespace App\Models\API;

use App\Models\Photo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class APIPhoto extends Photo
{
    use HasFactory;

    protected $table = 'photos';

    protected $appends = ['type'];

    /**
     * Append type => web to an image when loaded from web
     *
     * @return string
     */
    public function getTypeAttribute () : string
    {
        return 'web';
    }
}
