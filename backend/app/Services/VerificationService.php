<?php

namespace App\Services;

use App\Mail\VerificationCodeMail;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class VerificationService
{
    /** Génère un code à 6 chiffres, le stocke (30 min) et l'envoie par email. */
    public function issue(User $user): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $user->update([
            'verification_code'            => $code,
            'verification_code_expires_at' => now()->addMinutes(30),
        ]);

        $orgName = $user->organisation?->nom ?? 'StockPilot';

        // Envoi de l'email (échoue silencieusement si le mailer n'est pas configuré).
        try {
            Mail::to($user->email)->send(new VerificationCodeMail($code, $orgName));
        } catch (\Throwable $e) {
            Log::error('[Verification] Envoi email échoué : ' . $e->getMessage());
        }

        // Toujours loggé → permet de tester sans SMTP configuré.
        Log::info("[Verification] Code pour {$user->email} : {$code}");

        return $code;
    }

    /** Vérifie le code saisi ; en cas de succès, marque l'email comme vérifié. */
    public function verify(User $user, string $code): bool
    {
        if (is_null($user->verification_code)) {
            return true; // déjà vérifié
        }

        if (! hash_equals($user->verification_code, trim($code))) {
            return false;
        }

        if ($user->verification_code_expires_at && $user->verification_code_expires_at->isPast()) {
            return false;
        }

        $user->update([
            'email_verified_at'            => now(),
            'verification_code'            => null,
            'verification_code_expires_at' => null,
        ]);

        return true;
    }
}
