<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LeaderboardResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'period' => $request->period ?? 'all_time',
            'leaders' => collect($this->resource)->map(fn($user, $index) => [
                'rank' => $index + 1,
                'user_id' => $user['user_id'],
                'display_name' => $user['display_name'],
                'stats' => [
                    'photos' => $user['photo_count'],
                    'xp' => $user['total_xp'],
                    'litter' => $user['total_litter'],
                ],
            ]),
        ];
    }
}
