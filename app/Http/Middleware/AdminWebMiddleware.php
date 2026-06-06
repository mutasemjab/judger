<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminWebMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::guard('admin_web')->check()) {
            return redirect()->route('admin.showlogin');
        }

        if (!Auth::guard('admin_web')->user()->isAdmin()) {
            Auth::guard('admin_web')->logout();
            return redirect()->route('admin.showlogin')->with('error', 'Access denied.');
        }

        return $next($request);
    }
}
