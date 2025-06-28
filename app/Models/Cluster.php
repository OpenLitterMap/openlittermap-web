<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cluster extends Model
{
    use HasFactory;

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = [
        'tile_key' => 'integer',
        'zoom' => 'integer',
        'year' => 'integer',
        'cell_x' => 'integer',
        'cell_y' => 'integer',
        'lat' => 'float',
        'lon' => 'float',
        'point_count' => 'integer',
    ];

    /**
     * Get the primary key for the model.
     *
     * @return array
     */
    public function getKeyName()
    {
        return ['tile_key', 'zoom', 'year', 'cell_x', 'cell_y'];
    }

    /**
     * Get the value indicating whether the IDs are incrementing.
     *
     * @return bool
     */
    public function getIncrementing()
    {
        return false;
    }

    /**
     * Get clusters within a bounding box
     *
     * @param float $minLon
     * @param float $minLat
     * @param float $maxLon
     * @param float $maxLat
     * @param int $zoom
     * @return \Illuminate\Support\Collection
     */
    public static function withinBounds(float $minLon, float $minLat, float $maxLon, float $maxLat, int $zoom)
    {
        return static::where('zoom', $zoom)
            ->whereBetween('lon', [$minLon, $maxLon])
            ->whereBetween('lat', [$minLat, $maxLat])
            ->get();
    }
}
