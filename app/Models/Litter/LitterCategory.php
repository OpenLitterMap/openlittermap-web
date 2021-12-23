<?php

namespace App\Models\Litter;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

abstract class LitterCategory extends Model
{
    use HasFactory;

    protected $guarded = [];

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

    /**
     * Return a string of key => value pairs,
     *
     * Where "table" is the name of the category
     *
     * Where key is the translation key
     *
     * and value is the number of litter items for that key
     */
    public function translate ()
    {
        $string = '';

        foreach ($this->types() as $type)
        {
            if ($this->$type)
            {
                $className = $this->table == 'arts' ? 'art' : $this->table;

                $string .= $className . '.' . $type . ' ' . $this->$type . ',';
            }
        }

        return $string;
    }

    public static abstract function types(): array;
}
