<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @deprecated Use AdminQueueController with pagination instead.
 */
class GoBackOnePhotoController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Deprecated. Use GET /api/admin/photos with pagination.',
        ], 301);
    }
}
