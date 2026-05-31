<?php

namespace App\Http\Middleware;

use App\Enums\AccountStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->account_status !== AccountStatus::Active) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is ' . ($user->account_status->value ?? 'inactive') . '. Please contact support.',
                'data' => null,
            ], 403);
        }

        return $next($request);
    }
}
