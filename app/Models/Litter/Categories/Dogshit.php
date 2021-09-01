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

    public function typesForExport(): array
    {
        return [
            'poo' => 'dog_poo',
            'poo_in_bag' => 'dog_poo_in_a_bag'
        ];
    }
}
