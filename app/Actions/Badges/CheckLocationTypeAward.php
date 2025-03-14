<?php

namespace App\Actions\Badges;

use App\Jobs\Badges\GenerateBadgeImage;
use App\Models\Badges\Badge;
use App\Models\Litter\Tags\PhotoTag;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheckLocationTypeAward
{
    protected int $userId;

    public function checkLandUseAward(int $userId, PhotoTag $photoTag)
    {
        $this->userId = $userId;

        $photo = $photoTag->photo;

        if (!$photo->lat || !$photo->lon) {
            Log::warning("Photo {$photo->id} has no coordinates.");
            return;
        }

        $landUseTypes = $this->fetchLanduseTypes($photo->lat, $photo->lon);
        Log::info(['landUseTypes' => $landUseTypes]);

        if (empty($landUseTypes)) {
            Log::info('No landuse types found for coordinates.', [
                'lat' => $photo->lat,
                'lon' => $photo->lon
            ]);
            return;
        }

        $this->createAwardBadges($landUseTypes);
    }

    protected function fetchLandUseTypes($lat, $lon): array
    {
        $query = "[out:json];(way(around:50,$lat,$lon)[landuse];way(around:50,$lat,$lon)[natural];);out;";

        $response = Http::asForm()->post('https://overpass-api.de/api/interpreter', [
            'data' => $query
        ]);

        $elements = $response->json('elements');

        return $this->extractLanduseTypes($elements);
    }

    protected function extractLanduseTypes(array $elements): array
    {
        $types = [];

        foreach ($elements as $element) {
            if (isset($element['tags']['landuse'])) {
                $types[] = strtolower(trim($element['tags']['landuse']));
            }

            if (isset($element['tags']['natural'])) {
                $types[] = strtolower($element['tags']['natural']);
            }
        }

        return array_unique($types);
    }

    protected function createAwardBadges (array $landUseTypes): void
    {
        foreach ($landUseTypes as $landUseType) {

            $badge = Badge::firstOrCreate(['type' => 'landuse', 'subtype' => $landUseType]);

            if ($badge->filename === null) {
                dispatch (new GenerateBadgeImage($badge));
            }

            $badge->users()->syncWithoutDetaching($this->userId);
        }
    }
}
