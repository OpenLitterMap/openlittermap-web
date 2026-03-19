<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Paginated Locations Resource
 */
class PaginatedLocationsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'data' => LocationListResource::collection($this['locations']),
            'pagination' => $this['pagination'],
            'totals' => $this['totals'],
        ];
    }
}
