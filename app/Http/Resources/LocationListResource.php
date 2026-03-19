<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LocationListResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->country ?? $this->state ?? $this->city,
            'type' => $this->getLocationType(),
            'verified' => $this->manual_verify,

            // Core metrics only
            'total_litter' => $this->total_litter ?? $this->total_litter_redis,
            'total_photos' => $this->total_photos ?? $this->total_photos_redis,
            'total_contributors' => $this->total_contributors ?? $this->total_contributors_redis,
            'percentage_litter' => $this->percentage_litter ?? 0,
            'percentage_photos' => $this->percentage_photos ?? 0,

            // Ranking score if present (from ZSET ranking)
            'rank_score' => $this->when(
                isset($this->rank_score),
                $this->rank_score
            ),

            'last_uploaded_at' => $this->last_uploaded_at,
        ];
    }

    private function getLocationType(): string
    {
        if (isset($this->country) && !isset($this->state)) return 'country';
        if (isset($this->state) && !isset($this->city)) return 'state';
        if (isset($this->city)) return 'city';
        return 'unknown';
    }
}
