<?php

namespace App\Http\Middleware;

use Illuminate\Support\Facades\Auth;
use Closure;
use Illuminate\Http\Request;

class CanAddBoxes
{
    /**
     * The user can add boxes to images
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (Auth::user() && Auth::user()->can_bbox)
        {
            return $next($request);
        }

        return redirect('/');
    }
}
