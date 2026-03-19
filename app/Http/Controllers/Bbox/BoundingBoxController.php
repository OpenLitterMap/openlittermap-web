<?php

namespace App\Http\Controllers\Bbox;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @deprecated Retired in v5. All endpoints return 410 Gone.
 *
 * Was: bounding box annotation pipeline using v4 category FK columns,
 * AddTagsTrait, and broken TagsVerifiedByAdmin (wrong 1-arg signature).
 */
class BoundingBoxController extends Controller
{
    private const MESSAGE = 'Bounding box endpoints retired in v5. Use the standard tagging flow.';

    public function index(): JsonResponse
    {
        return response()->json(['message' => self::MESSAGE], 410);
    }

    public function create(Request $request): JsonResponse
    {
        return response()->json(['message' => self::MESSAGE], 410);
    }

    public function skip(Request $request): JsonResponse
    {
        return response()->json(['message' => self::MESSAGE], 410);
    }

    public function updateTags(Request $request): JsonResponse
    {
        return response()->json(['message' => self::MESSAGE], 410);
    }

    public function wrongTags(Request $request): JsonResponse
    {
        return response()->json(['message' => self::MESSAGE], 410);
    }
}
