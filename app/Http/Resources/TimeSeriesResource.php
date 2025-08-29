<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TimeSeriesResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'period' => $request->period ?? 'daily',
            'data' => $this->resource,
        ];
    }
}
