<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClientService
{
    /**
     * Outstanding balance (somme des restes dus) for a client.
     */
    public function solde(int $clientId): float
    {
        $total = Sale::where('client_id', $clientId)
            ->where('statut', '!=', Sale::STATUT_ANNULEE)
            ->selectRaw('COALESCE(SUM(total_ttc - montant_regle), 0) as due')
            ->value('due');

        return round((float) $total, 3);
    }

    /**
     * Record a payment from a client and allocate it to the oldest unpaid
     * sales first (FIFO). Returns the created payment row.
     */
    public function recordPayment(
        int    $clientId,
        float  $montant,
        int    $userId,
        string $modePaiement = 'especes',
        ?string $note = null,
    ): ClientPayment {
        if ($montant <= 0) {
            throw ValidationException::withMessages([
                'montant' => 'Le montant doit être supérieur à zéro.',
            ]);
        }

        return DB::transaction(function () use ($clientId, $montant, $userId, $modePaiement, $note) {
            $client = Client::lockForUpdate()->findOrFail($clientId);

            $due = $this->solde($client->id);
            if ($due <= 0) {
                throw ValidationException::withMessages([
                    'montant' => 'Ce client n\'a aucun montant dû.',
                ]);
            }

            // On n'encaisse jamais plus que le solde dû.
            $applied   = round(min($montant, $due), 3);
            $remaining = $applied;

            $sales = Sale::where('client_id', $client->id)
                ->where('statut', '!=', Sale::STATUT_ANNULEE)
                ->whereRaw('montant_regle < total_ttc')
                ->orderBy('date_vente')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($sales as $sale) {
                if ($remaining <= 0) break;

                $resteVente = round((float) $sale->total_ttc - (float) $sale->montant_regle, 3);
                $part       = round(min($resteVente, $remaining), 3);

                $sale->update(['montant_regle' => round((float) $sale->montant_regle + $part, 3)]);
                $remaining = round($remaining - $part, 3);
            }

            return ClientPayment::create([
                'client_id'     => $client->id,
                'user_id'       => $userId,
                'montant'       => $applied,
                'mode_paiement' => $modePaiement,
                'note'          => $note,
                'date_paiement' => now(),
            ]);
        });
    }
}
