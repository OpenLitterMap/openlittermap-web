<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Location Resource - Single location response
 */
class LocationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->country ?? $this->state ?? $this->city,
            'type' => $this->getLocationType(),
            'verified' => $this->manual_verify,

            // Core metrics
            'metrics' => [
                'total_litter' => $this->total_litter ?? $this->total_litter_redis,
                'total_photos' => $this->total_photos ?? $this->total_photos_redis,
                'total_contributors' => $this->total_contributors ?? $this->total_contributors_redis,
                'percentage_litter' => $this->percentage_litter ?? 0,
                'percentage_photos' => $this->percentage_photos ?? 0,
            ],

            // Averages
            'averages' => [
                'litter_per_user' => $this->avg_litter_per_user ?? 0,
                'photos_per_user' => $this->avg_photos_per_user ?? 0,
            ],

            // Optional breakdowns (only if present)
            'breakdowns' => $this->when(
                isset($this->category_breakdown) || isset($this->object_breakdown),
                [
                    'categories' => $this->category_breakdown ?? [],
                    'objects' => $this->object_breakdown ?? [],
                    'brands' => $this->brand_breakdown ?? [],
                ]
            ),

            // Activity
            'last_uploaded_at' => $this->last_uploaded_at,
            'updated_at' => $this->updated_at,
            'updated_at_human' => $this->updatedAtDiffForHumans,

            // Relationships
            'creator' => $this->whenLoaded('creator', fn() => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'last_uploader' => $this->whenLoaded('lastUploader', fn() => [
                'id' => $this->lastUploader->id,
                'name' => $this->lastUploader->name,
            ]),
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

/**
 * Leaderboard Resource (for v1.1)
 */

