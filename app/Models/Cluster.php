<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Cluster extends Model
{
    use HasFactory;

    protected $fillable = [
        'lat',
        'lon',
        'point_count',
        'point_count_abbreviated',
        'geohash',
        'zoom'
    ];

    public function scopeRadius($latitude, $longitude, $radius)
    {
        $r = 6371;  // earth's mean radius, km

        // first-cut bounding box (in degrees)
        $maxLat = $latitude + rad2deg($radius / $r);
        $minLat = $latitude - rad2deg($radius / $r);
        $maxLon = $longitude + rad2deg(asin($radius / $r) / cos(deg2rad($latitude)));
        $minLon = $longitude - rad2deg(asin($radius / $r) / cos(deg2rad($latitude)));

        $latName = "lat";
        $lonName = "lon";

        $lat = deg2rad($latitude);
        $lng = deg2rad($longitude);


        $query = Cluster::addSelect(DB::raw('*,acos(least(greatest(sin('.$lat.')*sin(radians('.$latName.')) + cos('.$lat.')*cos(radians('.$latName.'))*cos(radians('.$lonName.')'.($lng < 0 ? '+'.abs($lng) : '-'.$lng).'), -1), 1)) * '.$r.' As distance'))
            ->fromSub(function ($query) use ($maxLat, $minLat, $maxLon, $minLon, $latName, $lonName) {
                $query->from($this->getTable())
                    ->whereBetween($latName, [$minLat, $maxLat])
                    ->whereBetween($lonName, [$minLon, $maxLon]);
            }, $this->getTable());
        if ($lng < 0) {
            $query->whereRaw(
                'acos(least(greatest(sin(?)*sin(radians('.$latName.')) + cos(?)*cos(radians('.$latName.'))*cos(radians('.$lonName.')+?), -1), 1)) * ? < ?',
                [$lat, $lat, abs($lng), $r, $radius]
            );
        } else {
            $query->whereRaw(
                'acos(least(greatest(sin(?)*sin(radians('.$latName.')) + cos(?)*cos(radians('.$latName.'))*cos(radians('.$lonName.')-?), -1), 1)) * ? < ?',
                [$lat, $lat, $lng, $r, $radius]
            );
        }
        $query->orderBy("distance",'asc');

        return $query;
    }
}
