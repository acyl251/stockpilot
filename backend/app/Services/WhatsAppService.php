<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    /**
     * Send a WhatsApp message via the configured driver. Always returns a
     * wa.me link so the message can be sent manually even without a provider.
     *
     * @return array{ok: bool, driver: string, to: string, wa_link: string, status: string}
     */
    public function send(string $phone, string $message): array
    {
        $to     = $this->normalize($phone);
        $driver = config('whatsapp.driver', 'log');
        $waLink = 'https://wa.me/' . $to . '?text=' . rawurlencode($message);

        $ok     = true;
        $status = 'logged';

        if ($driver === 'twilio') {
            [$ok, $status] = $this->sendViaTwilio($to, $message);
        } else {
            Log::info("[WhatsApp][log] → +{$to} : {$message}");
        }

        return [
            'ok'      => $ok,
            'driver'  => $driver,
            'to'      => $to,
            'wa_link' => $waLink,
            'status'  => $status,
        ];
    }

    /** Normalise un numéro en format international sans « + » (ex : 21629123456). */
    public function normalize(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        $cc     = (string) config('whatsapp.country_code', '216');

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }
        // Numéro local (8 chiffres en Tunisie) → on préfixe l'indicatif pays.
        if (strlen($digits) === 8) {
            $digits = $cc . $digits;
        }

        return $digits;
    }

    /** @return array{0: bool, 1: string} */
    private function sendViaTwilio(string $to, string $message): array
    {
        $sid   = config('whatsapp.twilio.sid');
        $token = config('whatsapp.twilio.token');
        $from  = config('whatsapp.twilio.from');

        if (! $sid || ! $token || ! $from) {
            Log::warning('[WhatsApp][twilio] Identifiants manquants — message non envoyé.');
            return [false, 'twilio_not_configured'];
        }

        try {
            $res = Http::asForm()
                ->withBasicAuth($sid, $token)
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                    'From' => 'whatsapp:' . $from,
                    'To'   => 'whatsapp:+' . $to,
                    'Body' => $message,
                ]);

            return [$res->successful(), $res->successful() ? 'sent' : 'failed'];
        } catch (\Throwable $e) {
            Log::error('[WhatsApp][twilio] ' . $e->getMessage());
            return [false, 'error'];
        }
    }
}
