<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Closure;
use Illuminate\Support\Facades\Auth;

class IsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @return mixed
     */
    public function handle ($request, Closure $next)
    {
        if (Auth::user() && (Auth::user()->hasRole('admin') || Auth::user()->hasRole('superadmin')))
        {
            return $next($request);
        }

        return redirect('/');
    }
}
