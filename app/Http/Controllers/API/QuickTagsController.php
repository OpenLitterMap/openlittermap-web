<?php

namespace App\Http\Controllers\API;

use App\Actions\QuickTags\SyncQuickTagsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SyncQuickTagsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuickTagsController extends Controller
{
    /**
     * GET /api/user/quick-tags
     */
    public function index(Request $request): JsonResponse
    {
        $tags = $request->user()->quickTags()->get();

        return response()->json([
            'success' => true,
            'tags' => $tags,
        ]);
    }

    /**
     * PUT /api/user/quick-tags
     */
    public function update(SyncQuickTagsRequest $request, SyncQuickTagsAction $action): JsonResponse
    {
        $tags = $action->run(
            $request->user(),
            $request->validated('tags')
        );

        return response()->json([
            'success' => true,
            'tags' => $tags,
        ]);
    }

    /**
     * GET /api/v3/user/top-tags?limit=20
     *
     * Returns the user's most-tagged items grouped by CLO + type,
     * with the dominant brand (>50%) included when applicable.
     */
    public function topTags(Request $request): JsonResponse
    {
        $limit = min((int) ($request->query('limit', 20)), 30);
        $userId = $request->user()->id;

        // Get top CLO + type combos by quantity
        $rows = DB::table('photo_tags as pt')
            ->join('photos as p', 'p.id', '=', 'pt.photo_id')
            ->join('category_litter_object as clo', 'clo.id', '=', 'pt.category_litter_object_id')
            ->join('categories as c', 'c.id', '=', 'clo.category_id')
            ->join('litter_objects as lo', 'lo.id', '=', 'clo.litter_object_id')
            ->leftJoin('litter_object_types as lot', 'lot.id', '=', 'pt.litter_object_type_id')
            ->where('p.user_id', $userId)
            ->whereNotNull('pt.category_litter_object_id')
            ->select(
                'pt.category_litter_object_id as clo_id',
                'c.key as category_key',
                'lo.key as object_key',
                'pt.litter_object_type_id as type_id',
                'lot.key as type_key',
                DB::raw('SUM(pt.quantity) as total')
            )
            ->groupBy('pt.category_litter_object_id', 'c.key', 'lo.key', 'pt.litter_object_type_id', 'lot.key')
            ->havingRaw('SUM(pt.quantity) >= 3')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return response()->json(['success' => true, 'tags' => []]);
        }

        // Find dominant brand (>50%) for each CLO + type combo
        $cloTypeKeys = $rows->map(fn ($r) => $r->clo_id . ':' . ($r->type_id ?? 'null'))->toArray();

        $brandRows = DB::table('photo_tag_extra_tags as ptet')
            ->join('photo_tags as pt', 'pt.id', '=', 'ptet.photo_tag_id')
            ->join('photos as p', 'p.id', '=', 'pt.photo_id')
            ->join('brandslist as bl', 'bl.id', '=', 'ptet.tag_type_id')
            ->where('ptet.tag_type', 'brand')
            ->where('p.user_id', $userId)
            ->whereNotNull('pt.category_litter_object_id')
            ->select(
                'pt.category_litter_object_id as clo_id',
                'pt.litter_object_type_id as type_id',
                'ptet.tag_type_id as brand_id',
                'bl.key as brand_key',
                DB::raw('SUM(ptet.quantity) as brand_total')
            )
            ->groupBy('pt.category_litter_object_id', 'pt.litter_object_type_id', 'ptet.tag_type_id', 'bl.key')
            ->orderByDesc('brand_total')
            ->get();

        // Build lookup: "clo_id:type_id" → best brand (only if >50%)
        $brandLookup = [];
        $groupTotals = $rows->keyBy(fn ($r) => $r->clo_id . ':' . ($r->type_id ?? 'null'))
            ->map(fn ($r) => (int) $r->total);

        foreach ($brandRows as $br) {
            $key = $br->clo_id . ':' . ($br->type_id ?? 'null');

            if (! isset($groupTotals[$key])) {
                continue;
            }

            // Only include if this brand accounts for >50% of the group
            if (((int) $br->brand_total / $groupTotals[$key]) > 0.5) {
                if (! isset($brandLookup[$key]) || (int) $br->brand_total > $brandLookup[$key]['brand_total']) {
                    $brandLookup[$key] = [
                        'brand_id' => (int) $br->brand_id,
                        'brand_key' => $br->brand_key,
                        'brand_total' => (int) $br->brand_total,
                    ];
                }
            }
        }

        $tags = $rows->map(function ($row) use ($brandLookup) {
            $key = $row->clo_id . ':' . ($row->type_id ?? 'null');
            $brand = $brandLookup[$key] ?? null;

            return [
                'clo_id' => (int) $row->clo_id,
                'category_key' => $row->category_key,
                'object_key' => $row->object_key,
                'type_id' => $row->type_id ? (int) $row->type_id : null,
                'type_key' => $row->type_key,
                'brand_id' => $brand ? $brand['brand_id'] : null,
                'brand_key' => $brand ? $brand['brand_key'] : null,
                'total' => (int) $row->total,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'tags' => $tags,
        ]);
    }
}
