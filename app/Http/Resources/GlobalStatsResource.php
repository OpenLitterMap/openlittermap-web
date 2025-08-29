<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class GlobalStatsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'totals' => [
                'litter' => $this['total_litter'],
                'photos' => $this['total_photos'],
                'contributors' => $this['total_contributors'],
                'countries' => $this['total_countries'],
            ],

            'level' => [
                'current' => $this['level'],
                'xp' => [
                    'current' => $this['current_xp'],
                    'previous' => $this['previous_xp'],
                    'next' => $this['next_xp'],
                ],
                'progress' => $this['progress'],
            ],

            'top_categories' => $this['top_categories'],
            'top_brands' => $this['top_brands'],
        ];
    }
}
