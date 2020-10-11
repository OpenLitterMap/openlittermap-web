<?php

namespace App\Http\Controllers;

use App\Models\Photo;
use GeoHash;
use Illuminate\Http\Request;

class GlobalMapController extends Controller
{
    private $bitss = [16, 8, 4, 2, 1];
    private $neighbors = [];
    private $borders = [];

    private $coding = "0123456789bcdefghjkmnpqrstuvwxyz";
    private $codingMap = [];

    public function __construct()
    {
        $this->neighbors['right']['even'] = 'bc01fg45238967deuvhjyznpkmstqrwx';
        $this->neighbors['left']['even'] = '238967debc01fg45kmstqrwxuvhjyznp';
        $this->neighbors['top']['even'] = 'p0r21436x8zb9dcf5h7kjnmqesgutwvy';
        $this->neighbors['bottom']['even'] = '14365h7k9dcfesgujnmqp0r2twvyx8zb';

        $this->borders['right']['even'] = 'bcfguvyz';
        $this->borders['left']['even'] = '0145hjnp';
        $this->borders['top']['even'] = 'prxz';
        $this->borders['bottom']['even'] = '028b';

        $this->neighbors['bottom']['odd'] = $this->neighbors['left']['even'];
        $this->neighbors['top']['odd'] = $this->neighbors['right']['even'];
        $this->neighbors['left']['odd'] = $this->neighbors['bottom']['even'];
        $this->neighbors['right']['odd'] = $this->neighbors['top']['even'];

        $this->borders['bottom']['odd'] = $this->borders['left']['even'];
        $this->borders['top']['odd'] = $this->borders['right']['even'];
        $this->borders['left']['odd'] = $this->borders['bottom']['even'];
        $this->borders['right']['odd'] = $this->borders['top']['even'];

        //build map from encoding char to 0 padded bitfield
        for ($i = 0; $i < 32; $i++) {
            $this->codingMap[substr($this->coding, $i, 1)] = str_pad(decbin($i), 5, "0", STR_PAD_LEFT);
        }
    }

    public $zoomToGeoHashPrecision = [
        0 => 1,
        1 => 1,
        2 => 1,
        3 => 1,
        4 => 2,
        5 => 2,
        6 => 2,
        7 => 2,
        8 => 3,
        9 => 3,
        10 => 4,
        11 => 4,
        12 => 5,
        13 => 5,
        14 => 5,
        15 => 6,
        16 => 6,
        17 => 6,
        18 => 7,
        19 => 7,
        20 => 7,
        21 => 7
      ];

    private function calculateAdjacent($srcHash, $dir)
    {
        $srcHash = strtolower($srcHash);
        $lastChr = $srcHash[strlen($srcHash) - 1];
        $type = (strlen($srcHash) % 2) ? 'odd' : 'even';
        $base = substr($srcHash, 0, strlen($srcHash) - 1);

        if (strpos($this->borders[$dir][$type], $lastChr) !== false) {
            $base = $this->calculateAdjacent($base, $dir);
        }

        return $base . $this->coding[strpos($this->neighbors[$dir][$type], $lastChr)];
    }

    public function neighbors ($srcHash)
    {
        $geohashPrefix = substr($srcHash, 0, strlen($srcHash) - 1);

        $neighbors['top'] = $this->calculateAdjacent($srcHash, 'top');
        $neighbors['bottom'] = $this->calculateAdjacent($srcHash, 'bottom');
        $neighbors['right'] = $this->calculateAdjacent($srcHash, 'right');
        $neighbors['left'] = $this->calculateAdjacent($srcHash, 'left');

        $neighbors['topleft'] = $this->calculateAdjacent($neighbors['left'], 'top');
        $neighbors['topright'] = $this->calculateAdjacent($neighbors['right'], 'top');
        $neighbors['bottomright'] = $this->calculateAdjacent($neighbors['right'], 'bottom');
        $neighbors['bottomleft'] = $this->calculateAdjacent($neighbors['left'], 'bottom');
        $neighbors['center'] = $srcHash;

        return $neighbors;
    }

    /**
     * Query database spatially by geohash
     */
    public function clusters ()
    {
        // get center of the bbox, assuming the earth is flat
        $center_lat = (request()->top + request()->bottom) / 2;
        $center_lon = (request()->left + request()->right) / 2;

        // zoom level will determine what level of geohash precision to use
        $precision = $this->zoomToGeoHashPrecision[request()->zoom];

        // Get the center of the bounding box, as a geohash
        $center_geohash = GeoHash::encode($center_lat, $center_lon, $precision); // precision 0 will return the full geohash
        // \Log::info(['center_geohash', $center_geohash]);

        $geos = [];

        if (request()->zoom > 3)
        {
            // get the neighbour geohashes from our center geohash
            $ns = $this->neighbors($center_geohash);
            foreach ($ns as $n) array_push($geos, $n);
        }

        // global keys
        else $geos = ['c', 'f', 'g', 'u', 'v', 'y', 'z', '9', 'd', 'e', 's', 't', 'w', 'x', '6', 'k', 'q', 'r'];
        \Log::info(['geos', $geos]);

        $hulls = [];
        foreach ($geos as $geo)
        {
            $photos = Photo::select('lat', 'lon')
                ->where([
                    'verified' => 2,
                    ['geohash', 'like', $geo . '%'] // starts with
                ])
                ->get()
                ->toArray();

            $hull = $this->convexHull($photos); // returns the centroid of the hull

            array_push($hulls, $hull);
        }

//        When zoom is 17+, we want to get individual coordinates and show points
//        $photos = Photo::select('lat', 'lon')
//            ->where(function ($q) use ($geos)
//            {
//                 foreach ($geos as $geo)
//                 {
//                     $q->orWhere([
//                         'verified' => 2,
//                         ['geohash', 'like', $geo . '%'] // starts with
//                     ]);
//                 }
//            })
//            ->get()
//
//        $geojson = [
//            'type'      => 'FeatureCollection',
//            'features'  => []
//        ];
//
//        foreach ($photos as $photo)
//        {
//            $feature = [
//                'type' => 'Feature',
//                'geometry' => [
//                    'type' => 'Point',
//                    'coordinates' => [$photo->lon, $photo->lat]
//                ],
//
//                'properties' => [
//                    'filename' => $photo->filename,
//                    'result_string' => $photo->result_string,
//                    'lat' => $photo->lat,
//                    'lon' => $photo->lon
//                ]
//            ];
//
//            // Add features to feature collection array
//            array_push($geojson["features"], $feature);
//        }
//
//        json_encode($geojson, JSON_NUMERIC_CHECK);

        return ['hulls' => $hulls];
    }

    private function convexHull ($points)
    {
        /* Ensure point doesn't rotate the incorrect direction as we process the hull halves */
        $cross = function($o, $a, $b) {
             // return ($a[0] - $o[0]) * ($b[1] - $o[1]) - ($a[1] - $o[1]) * ($b[0] - $o[0]);
             return ($a['lon'] - $o['lon']) * ($b['lat'] - $o['lat']) - ($a['lat'] - $o['lat']) * ($b['lon'] - $o['lon']);
        };

        $pointCount = count($points);
        sort($points);

        if ($pointCount > 1)
        {
            $n = $pointCount;
            $k = 0;
            $h = array();

            /* Build lower portion of hull */
            for ($i = 0; $i < $n; ++$i)
            {
                while ($k >= 2 && $cross($h[$k - 2], $h[$k - 1], $points[$i]) <= 0)
                    $k--;
                $h[$k++] = $points[$i];
            }

            /* Build upper portion of hull */
            for ($i = $n - 2, $t = $k + 1; $i >= 0; $i--)
            {
                while ($k >= $t && $cross($h[$k - 2], $h[$k - 1], $points[$i]) <= 0)
                    $k--;
                $h[$k++] = $points[$i];
            }

            /* Remove all vertices after k as they are inside of the hull */
            if ($k > 1)
            {
                /* If you don't require a self closing polygon, change $k below to $k-1 */
                $h = array_splice($h, 0, $k);
            }

            // We now have the outer vertices of the convex hull $h
            // We want to average them to get the center
            $lat = 0.0;
            $lon = 0.0;
            $count = 0;

            foreach ($h as $v)
            {
                $lat += $v['lat'];
                $lon += $v['lon'];
                $count++;
            }

            $lat = $lat / $count;
            $lon = $lon / $count;

            return ['lat' => $lat, 'lon' => $lon, 'count' => $n];

            // return $h; -> all vertices for the hull
        }

        else if ($pointCount <= 1) return $points;

        else return null;
    }

}
