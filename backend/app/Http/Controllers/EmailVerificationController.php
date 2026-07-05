<?php

namespace App\Http\Controllers;

use App\Models\DemoRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    /** GET /api/verify-email/{token} — public, no auth */
    public function __invoke(Request $request, string $token): JsonResponse
    {
        $demo = DemoRequest::where('email_token', $token)->first();

        if (! $demo) {
            return response()->json([
                'status'  => 'invalid',
                'message' => 'Lien invalide.',
            ], 404);
        }

        if ($demo->isEmailVerified()) {
            // Déjà confirmé → traiter comme succès (idempotent)
            return response()->json([
                'status'  => 'already_verified',
                'message' => 'Email déjà confirmé.',
                'prenom'  => $demo->prenom,
            ]);
        }

        if ($demo->isTokenExpired()) {
            return response()->json([
                'status'  => 'expired',
                'message' => 'Ce lien a expiré. Soumettez une nouvelle demande.',
            ], 410);
        }

        // Marquer comme vérifié → passe en attente de traitement admin
        $demo->update([
            'email_verified_at' => now(),
            'email_token'       => null,   // invalide le lien après usage
            'statut'            => 'en_attente',
        ]);

        return response()->json([
            'status'  => 'verified',
            'message' => 'Email confirmé. Nous vous contacterons sous 24h.',
            'prenom'  => $demo->prenom,
        ]);
    }
}
