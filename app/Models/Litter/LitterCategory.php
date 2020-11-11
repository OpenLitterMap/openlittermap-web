<?php

namespace App\Models\Litter;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LitterCategory extends Model
{
    use HasFactory;

    /**
     * Total amount of litter on any litter category model
     */
    public function total ()
    {
        $total = 0;

        foreach ($this->types() as $type)
        {
            if ($this->$type) $total += $this->$type;
        }

        return $total;
    }
}
