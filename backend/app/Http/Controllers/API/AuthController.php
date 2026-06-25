<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\VerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function login(Request $request, VerificationService $verification): JsonResponse
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

        // Email non vérifié → on (re)génère un code si besoin et on bloque l'accès.
        if ($user->needsEmailVerification()) {
            if (is_null($user->verification_code_expires_at) || $user->verification_code_expires_at->isPast()) {
                $verification->issue($user);
            }

            return response()->json([
                'verification_required' => true,
                'email'                 => $user->email,
                'message'               => 'Votre adresse email doit être vérifiée. Un code de confirmation vous a été envoyé.',
            ], 403);
        }

        return $this->tokenResponse($user);
    }

    /** Vérifie le code reçu par email et connecte l'utilisateur. */
    public function verifyEmail(Request $request, VerificationService $verification): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'code'  => 'required|string',
        ]);

        $user = User::withoutGlobalScopes()->where('email', $request->email)->first();

        if (! $user) {
            return $this->errorResponse('Compte introuvable.', 404);
        }

        if (! $user->needsEmailVerification()) {
            return $this->errorResponse('Cet email est déjà vérifié. Connectez-vous normalement.', 422);
        }

        if (! $verification->verify($user, $request->code)) {
            return $this->errorResponse('Code invalide ou expiré.', 422);
        }

        return $this->tokenResponse($user);
    }

    /** Renvoie un nouveau code de confirmation. */
    public function resendCode(Request $request, VerificationService $verification): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $user = User::withoutGlobalScopes()->where('email', $request->email)->first();

        if ($user && $user->needsEmailVerification()) {
            $verification->issue($user);
        }

        // Réponse neutre : ne révèle pas l'existence du compte.
        return response()->json([
            'message' => 'Si un compte non vérifié correspond à cet email, un nouveau code vient d\'être envoyé.',
        ]);
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
