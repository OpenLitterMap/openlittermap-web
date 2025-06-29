<?php

namespace App\Traits\GeoJson;

trait CreateGeoJsonPoints {
    /**
     * Convert a flat array of rows (each must contain lat & lon) into a
     * GeoJSON FeatureCollection.
     *
     * @param string $name
     * @param $features
     * @param bool $cluster
     * @return array<string,mixed>
     */
    public function createGeojsonPoints(string $name, $features, bool $cluster = false): array
    {
        $geojson = [
            'type' => 'FeatureCollection',
            'name' => $name,
            'crs'  => [
                'type'       => 'name',
                'properties' => ['name' => 'urn:ogc:def:crs:OGC:1.3:CRS84'],
            ],
            'features' => [],
        ];

        foreach ($features as $row) {
            $props = (array) $row;

            if ($cluster) {
                $props['cluster']                 = true;
                $props['point_count']             = (int) ($props['count'] ?? 0);
                $props['point_count_abbreviated'] = $this->abbreviate($props['point_count']);
                unset($props['count']);
            }

            $geojson['features'][] = [
                'type'       => 'Feature',
                'properties' => $props,
                'geometry'   => [
                    'type'        => 'Point',
                    'coordinates' => [$props['lon'], $props['lat']],
                ],
            ];
        }

        return $geojson;
    }

    /**
     * Very small helper: 8300 → “8.3k”, 1250000 → “1.3M”
     */
    private function abbreviate(int $n): string
    {
        if ($n >= 1_000_000) return round($n / 1_000_000, 1) . 'M';
        if ($n >=   1_000)   return round($n /   1_000, 1) . 'k';
        return (string) $n;
    }
}
