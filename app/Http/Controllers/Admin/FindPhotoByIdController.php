<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * @deprecated Forwards to AdminQueueController with photo_id filter.
 */
class FindPhotoByIdController extends Controller
{
    public function __invoke(Request $request)
    {
        return app(AdminQueueController::class)(
            $request->merge(['photo_id' => $request->id ?? $request->photoId])
        );
    }
}
