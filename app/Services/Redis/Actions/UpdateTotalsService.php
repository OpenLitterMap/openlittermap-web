<?php

namespace App\Services\Redis\Actions;

use App\Models\Photo;
use Illuminate\Support\Facades\Redis;

class UpdateTotalsService
{
    /**
     * Update global and scoped totals in Redis based on photo summary.
     */
    public function run(Photo $photo): void
    {
        // Fetch summary or default
        $summary = $photo->summary ?? [];

        // Totals section or defaults
        $t = $summary['totals'] ?? [];
        $totalTags   = $t['total_tags']    ?? 0;
        $totalCustom = $t['custom_tags']   ?? 0;
        $byCategory  = $t['by_category']   ?? [];

        // 1) Global totals: always increment photos
        Redis::hIncrBy('global:totals',      'photos',      1);
        Redis::hIncrBy('global:totals',      'tags',        $totalTags);
        Redis::hIncrBy('global:totals',      'custom_tags', $totalCustom);

        // 2) Global breakdowns
        // Categories
        foreach ($byCategory as $cat => $qty) {
            Redis::hIncrBy('global:totals:categories', $cat, $qty);
        }

        // Objects + materials/brands/custom_tags breakdown
        $tagsTree = $summary['tags'] ?? [];
        foreach ($tagsTree as $objects) {
            foreach ($objects as $objKey => $data) {
                Redis::hIncrBy('global:totals:objects', $objKey, $data['quantity']);
                foreach ($data['materials']   as $mat => $mqty) {
                    Redis::hIncrBy('global:totals:materials', $mat, $mqty);
                }
                foreach ($data['brands']      as $brand => $bqty) {
                    Redis::hIncrBy('global:totals:brands', $brand, $bqty);
                }
                foreach ($data['custom_tags'] as $ct  => $ctqty) {
                    Redis::hIncrBy('global:totals:custom_tags_breakdown', $ct, $ctqty);
                }
            }
        }

        // 3) Country-scoped totals
        $countryId = $photo->country?->id;
        if ($countryId) {
            $prefix = "country:{$countryId}";

            Redis::hIncrBy("{$prefix}:totals",      'photos',      1);
            Redis::hIncrBy("{$prefix}:totals",      'tags',        $totalTags);
            Redis::hIncrBy("{$prefix}:totals",      'custom_tags', $totalCustom);

            foreach ($byCategory as $cat => $qty) {
                Redis::hIncrBy("{$prefix}:totals:categories", $cat, $qty);
            }
            foreach ($tagsTree as $objects) {
                foreach ($objects as $objKey => $data) {
                    Redis::hIncrBy("{$prefix}:totals:objects", $objKey, $data['quantity']);
                }
            }
        }
    }
}
