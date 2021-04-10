<?php

namespace App\Models\Litter\Categories;

use App\Models\Litter\LitterCategory;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Dogshit extends LitterCategory
{
    use HasFactory;

    protected $table = 'dogshit';

    protected $fillable = [
        'poo',
        'poo_in_bag'
    ];

    /**
     * Pre-defined litter types available on this class
     */
    public function types ()
    {
        return [
            'poo',
            'poo_in_bag'
        ];
    }

}
