<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Mail\ConfirmDemoRequest;
use App\Models\DemoRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class DemoRequestController extends Controller
{
    /** Domaines email courants toujours acceptés sans vérification DNS */
    private const DNS_WHITELIST = [
        'gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com',
        'yahoo.fr', 'hotmail.fr', 'live.com', 'icloud.com',
        'live.fr', 'msn.com', 'orange.fr', 'sfr.fr', 'free.fr',
        'laposte.net', 'wanadoo.fr',
    ];

    /** POST /demo-request — public, no auth */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'prenom'        => 'required|string|max:100',
            'nom'           => 'required|string|max:100',
            'email'         => 'required|email|max:150',
            'telephone'     => 'nullable|string|max:30',
            'societe'       => 'required|string|max:150',
            'secteur'       => 'nullable|string|max:100',
            'plan_souhaite' => 'nullable|in:starter,essentiel,pro,entreprise',
            'message'       => 'nullable|string|max:1000',
        ]);

        // ── Niveau 1b : unicité — pas de demande active pour cet email ──────
        $existante = DemoRequest::where('email', $data['email'])
            ->whereIn('statut', ['pending_verification', 'en_attente'])
            ->first();

        if ($existante) {
            return response()->json([
                'message' => 'Une demande est déjà en cours pour cette adresse email. Vérifiez votre boîte mail ou contactez-nous au 27 650 255.',
                'errors'  => ['email' => ['Une demande est déjà en cours pour cet email.']],
            ], 422);
        }

        // ── Niveau 2 : vérification DNS du domaine ────────────────────────────
        $domain = substr(strrchr($data['email'], '@'), 1);
        if (! $this->isDomainValid($domain)) {
            return response()->json([
                'message' => 'L\'adresse email semble invalide. Vérifiez que vous avez saisi la bonne adresse.',
                'errors'  => ['email' => ['L\'adresse email semble invalide. Vérifiez que vous avez saisi la bonne adresse.']],
            ], 422);
        }

        // ── Niveau 3 : créer la demande + envoyer email de confirmation ───────
        $token = Str::random(64);

        $demo = DemoRequest::create(array_merge($data, [
            'statut'      => 'pending_verification',
            'email_token' => $token,
        ]));

        try {
            Mail::to($demo->email)->send(new ConfirmDemoRequest($demo));
        } catch (\Throwable $e) {
            \Log::error('Impossible d\'envoyer l\'email de confirmation', [
                'demo_id' => $demo->id,
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
        }

        return response()->json([
            'message' => 'Vérifiez votre email pour confirmer votre demande.',
            'id'      => $demo->id,
        ], 201);
    }

    /** GET /super-admin/demo-requests — super admin only */
    public function index(): JsonResponse
    {
        $requests = DemoRequest::orderByRaw(
            "CASE statut
               WHEN 'pending_verification' THEN 0
               WHEN 'en_attente' THEN 1
               WHEN 'traite'     THEN 2
               ELSE 3
             END"
        )
            ->orderByDesc('created_at')
            ->get();

        return response()->json($requests);
    }

    /** POST /super-admin/demo-requests/{id}/resend-email — resend confirmation email */
    public function resendEmail(int $id): JsonResponse
    {
        $demo = DemoRequest::findOrFail($id);

        if ($demo->statut !== 'pending_verification') {
            return response()->json(['message' => 'Cette demande n\'est pas en attente de vérification.'], 422);
        }

        // Regenerate token and reset the 48h expiry window
        $demo->email_token = Str::random(64);
        $demo->created_at  = now();
        $demo->save();

        try {
            Mail::to($demo->email)->send(new ConfirmDemoRequest($demo));
        } catch (\Throwable $e) {
            \Log::error('Impossible de renvoyer l\'email de confirmation', [
                'demo_id' => $demo->id,
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Email non envoyé : ' . $e->getMessage()], 500);
        }

        return response()->json(['message' => 'Email de confirmation renvoyé.', 'email' => $demo->email]);
    }

    /** PATCH /super-admin/demo-requests/{id} — update status */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'statut' => 'required|in:en_attente,traite,rejete',
        ]);

        $demo = DemoRequest::findOrFail($id);
        $demo->update($data);

        return response()->json($demo);
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function isDomainValid(string $domain): bool
    {
        if (in_array(strtolower($domain), self::DNS_WHITELIST, true)) {
            return true;
        }

        return checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A');
    }
}
