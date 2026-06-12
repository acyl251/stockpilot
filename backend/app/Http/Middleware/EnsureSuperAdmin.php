<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = app()->bound('current_user') ? app('current_user') : null;

        if (! $user || ! $user->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Cette action est réservée aux super-administrateurs.',
                'errors'  => null,
            ], 403);
        }

        return $next($request);
    }
}