<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * @deprecated Forwards to AdminQueueController (page 1, per_page 1).
 */
class GetNextImageToVerifyController extends Controller
{
    public function __invoke(Request $request)
    {
        return app(AdminQueueController::class)(
            $request->merge(['per_page' => 1, 'page' => 1])
        );
    }
}
