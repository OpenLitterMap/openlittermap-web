<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * @deprecated Replaced by AdminQueueController.
 */
class GoBackOnePhotoController extends Controller
{
    public function __invoke(Request $request)
    {
        abort(410, 'Use new admin endpoint.');
    }
}
