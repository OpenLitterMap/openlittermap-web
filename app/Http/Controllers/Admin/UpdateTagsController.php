<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AdminController;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @deprecated Forwards to AdminController::updateDelete().
 */
class UpdateTagsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return app(AdminController::class)->updateDelete($request);
    }
}
