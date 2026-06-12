<?php

namespace App\Http\Middleware;

use App\Models\Organisation;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthenticateTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (TokenExpiredException) {
            return $this->error('Votre session a expiré. Veuillez vous reconnecter.', 401);
        } catch (JWTException) {
            return $this->error('Token d\'authentification invalide ou manquant.', 401);
        }

        if (! $user) {
            return $this->error("L'utilisateur associé à ce token n'existe pas.", 401);
        }

        // Super admin has no tenant — bypass org check
        if ($user->role === 'super_admin') {
            app()->instance('current_organisation_id', null);
            app()->instance('current_user', $user);
            Auth::setUser($user);
            return $next($request);
        }

        // Bind organisation_id into the request context for Global Scope
        $organisationId = $user->organisation_id;

        if (! $organisationId) {
            return $this->error("Aucune organisation n'est associée à votre compte.", 403);
        }

        // Verify organisation is active
        $organisation = Organisation::withoutGlobalScopes()->find($organisationId);

        if (! $organisation || ! $organisation->actif) {
            return $this->error('Votre organisation est suspendue ou introuvable. Contactez le support.', 403);
        }

        // Store in app container for Global Scope to consume
        app()->instance('current_organisation_id', $organisationId);
        app()->instance('current_user', $user);

        Auth::setUser($user);

        return $next($request);
    }

    /** Uniform JSON error envelope, matching the global exception handler. */
    private function error(string $message, int $status): Response
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors'  => null,
        ], $status);
    }
}
