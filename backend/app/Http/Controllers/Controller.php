<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

abstract class Controller
{
    /**
     * Uniform API error response: { success: false, message, errors }.
     * Matches the global exception handler envelope (bootstrap/app.php).
     */
    protected function errorResponse(string $message, int $status = 400, mixed $errors = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
        ], $status);
    }

    /**
     * Returns true when the current user is an operator in a multi-PDV organisation.
     * In that case, only read operations and sales are allowed — all catalogue/stock
     * mutations are reserved for the admin.
     */
    protected function isRestrictedOperateur(): bool
    {
        $user = app('current_user');
        if ($user->role !== 'operateur') return false;
        // TenantScope is active in request context → count() filters on current org automatically.
        return \App\Models\PointDeVente::where('actif', true)->count() > 1;
    }
}
