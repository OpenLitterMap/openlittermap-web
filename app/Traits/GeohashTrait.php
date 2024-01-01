<?php

namespace App\Traits;

trait GeohashTrait
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

    /**
     * For each zoom level, we want to return a precision length to determine how many objects to retrieve
     */
    public $zoomToGeoHashPrecision = [
//        0 => 1,
//        1 => 1,
//        2 => 1,
//        3 => 1,
//        4 => 2,
//        5 => 2,
        6 => 2, // Our geohash filtering currently starts at this zoom level
        7 => 2,
        8 => 2,
        9 => 3,
        10 => 3,
        11 => 4,
        12 => 4,
        13 => 4,
        14 => 4,
        15 => 5, // Clustering filtering currently ends at this zoom level
        16 => 6, // Photos filtering currently begins at this zoom level
        17 => 6,
        18 => 6,
//        19 => 7,
//        20 => 7,
//        21 => 7
    ];

    private function calculateAdjacent($srcHash, $dir)
    {
        $srcHash = strtolower((string) $srcHash);
        $lastChr = $srcHash[strlen($srcHash) - 1];
        $type = (strlen($srcHash) % 2 !== 0) ? 'odd' : 'even';
        $base = substr($srcHash, 0, strlen($srcHash) - 1);

        if (strpos((string) $this->borders[$dir][$type], $lastChr) !== false) {
            $base = $this->calculateAdjacent($base, $dir);
        }

        return $base . $this->coding[strpos((string) $this->neighbors[$dir][$type], $lastChr)];
    }

    /**
     * Get neighbouring geohashes for our center geohash
     */
    public function neighbors ($srcHash)
    {
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
     * Handles edge cases when the zoom
     * is more than the max allowed level of 18
     *
     * @param int $precision
     */
    protected function getGeohashPrecision($precision): int
    {
        $precision = min($precision, 18);

        return $this->zoomToGeoHashPrecision[$precision];
    }
}
