<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TagsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'items' => collect($this['items'])->map(fn($item) => [
                'id' => $item['id'],
                'name' => $item['name'],
                'count' => $item['count'],
                'percentage' => $item['percentage'],
            ]),

            'totals' => [
                'litter' => $this['total'],
                'dimension' => $this['dimension_total'],
            ],

            'other' => $this['other'],
        ];
    }
}
