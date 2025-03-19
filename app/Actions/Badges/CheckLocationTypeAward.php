<?php

namespace App\Actions\Badges;

use App\Models\Badges\Badge;
use App\Models\Litter\Tags\PhotoTag;
use App\Jobs\Badges\GenerateBadgeImage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheckLocationTypeAward
{
    protected int $userId;

    public function checkLandUseAward(int $userId, PhotoTag $photoTag): void
    {
        $this->userId = $userId;

        $photo = $photoTag->photo;

        if (!$photo->lat || !$photo->lon) {
            Log::warning("Photo {$photo->id} has no coordinates.");
            return;
        }

        [$landUseTypes, $natureTypes] = $this->fetchLanduseNaturalTypes($photo->lat, $photo->lon);

        $this->createAwardBadges($landUseTypes, 'landuse');
        $this->createAwardBadges($natureTypes, 'nature');
    }

    protected function fetchLanduseNaturalTypes($lat, $lon): array
    {
        $query = "[out:json];(way(around:50,$lat,$lon)[landuse];way(around:50,$lat,$lon)[natural];);out;";

        $response = Http::asForm()->post('https://overpass-api.de/api/interpreter', [
            'data' => $query
        ]);

        $elements = $response->json('elements');
        $landUseTypes = $this->extractLandUseTypes($elements);
        $naturalTypes = $this->extractNaturalTypes($elements);

        return [$landUseTypes, $naturalTypes];
    }

    protected function extractLandUseTypes(array $elements): array
    {
        $types = [];

        foreach ($elements as $element) {
            if (isset($element['tags']['landuse'])) {
                $types[] = strtolower(trim($element['tags']['landuse']));
            }
        }

        return array_unique($types);
    }

    protected function extractNaturalTypes(array $elements): array
    {
        $types = [];

        foreach ($elements as $element) {
            if (isset($element['tags']['natural'])) {
                $types[] = strtolower(trim($element['tags']['natural']));
            }
        }

        return array_unique($types);
    }

    protected function createAwardBadges (array $landUseOrNatureTypes, string $type): void
    {
        foreach ($landUseOrNatureTypes as $landUseOrNatureType) {

            $badge = Badge::firstOrCreate(['type' => $type, 'subtype' => $landUseOrNatureType]);

            if ($badge->filename === null) {
                dispatch (new GenerateBadgeImage($badge));
            }

            $badge->users()->syncWithoutDetaching($this->userId);
        }
    }
}
