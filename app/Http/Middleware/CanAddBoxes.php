<?php

namespace App\Http\Middleware;

use Auth;
use Closure;
use Illuminate\Http\Request;

class CanAddBoxes
{
    /**
     * The user can add boxes to images
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
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
