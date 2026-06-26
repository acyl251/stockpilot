<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::withoutGlobalScopes()
            ->where('email', $request->email)
            ->first();

        if (! $user) {
            return $this->errorResponse('Email ou mot de passe incorrect.', 401);
        }

        if ($user->isLocked()) {
            return $this->errorResponse('Compte temporairement verrouillé suite à trop de tentatives. Réessayez dans quelques minutes.', 423);
        }

        if (! $user->actif) {
            return $this->errorResponse('Votre compte a été désactivé. Contactez votre administrateur.', 403);
        }

        if (! Hash::check($request->password, $user->password)) {
            $user->incrementLoginAttempts();
            return $this->errorResponse('Email ou mot de passe incorrect.', 401);
        }

        $user->resetLoginAttempts();

        return $this->tokenResponse($user);
    }

    private function tokenResponse(User $user): JsonResponse
    {
        $token = JWTAuth::fromUser($user);

        return response()->json([
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => config('jwt.ttl') * 60,
            'user'         => [
                'id'              => $user->id,
                'nom'             => $user->nom,
                'prenom'          => $user->prenom,
                'email'           => $user->email,
                'role'            => $user->role,
                'organisation_id' => $user->organisation_id,
            ],
        ]);
    }

    public function logout(): JsonResponse
    {
        JWTAuth::invalidate(JWTAuth::getToken());
        return response()->json(['message' => 'Déconnecté avec succès.']);
    }

    public function refresh(): JsonResponse
    {
        $token = JWTAuth::refresh(JWTAuth::getToken());
        return response()->json(['access_token' => $token, 'token_type' => 'bearer']);
    }

    public function me(): JsonResponse
    {
        $user = app('current_user');
        return response()->json($user->load('organisation.plan'));
    }
}
