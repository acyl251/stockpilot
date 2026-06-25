<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleService
{
    public function __construct(private StockService $stockService) {}

    /**
     * Record a point-of-sale ticket: validates stock, creates the sale and its
     * lines, decrements stock through StockService, and computes the change due.
     *
     * @param array  $items        Each: ['product_id' => int, 'quantite' => float]
     * @param string $modePaiement Sale::MODE_ESPECES | Sale::MODE_CARTE | Sale::MODE_CREDIT
     * @param float|null $montantPaye Amount tendered (cash) or down-payment (credit).
     * @param int|null   $clientId    Required for credit sales (deferred payment).
     */
    public function createSale(
        array   $items,
        int     $userId,
        string  $modePaiement = Sale::MODE_ESPECES,
        ?float  $montantPaye  = null,
        ?string $remiseType   = null,
        ?float  $remiseValeur = null,
        ?int    $clientId     = null,
    ): Sale {
        if (empty($items)) {
            throw ValidationException::withMessages([
                'items' => 'Le panier est vide.',
            ]);
        }

        if ($modePaiement === Sale::MODE_CREDIT && ! $clientId) {
            throw ValidationException::withMessages([
                'client_id' => 'Un client est requis pour une vente à crédit.',
            ]);
        }

        return DB::transaction(function () use ($items, $userId, $modePaiement, $montantPaye, $remiseType, $remiseValeur, $clientId) {
            $totalHt  = 0.0;
            $totalTtc = 0.0;
            $lines    = [];

            foreach ($items as $row) {
                /** @var Product $product */
                $product  = Product::lockForUpdate()->findOrFail($row['product_id']);
                $quantite = (float) $row['quantite'];

                if ($quantite <= 0) {
                    throw ValidationException::withMessages([
                        'items' => "Quantité invalide pour « {$product->nom} ».",
                    ]);
                }

                if ($product->quantite < $quantite) {
                    throw ValidationException::withMessages([
                        'items' => "Stock insuffisant pour « {$product->nom} » (disponible : {$product->quantite}).",
                    ]);
                }

                $puHt   = (float) $product->prix_vente_ht;
                $tva    = (float) $product->taux_tva;
                $puTtc  = round($puHt * (1 + $tva / 100), 3);
                $ligne  = round($puTtc * $quantite, 3);

                $totalHt  += round($puHt * $quantite, 3);
                $totalTtc += $ligne;

                $lines[] = [
                    'product'             => $product,
                    'designation'         => $product->nom,
                    'prix_achat_unitaire' => (float) $product->prix_achat_ht,
                    'quantite'          => $quantite,
                    'prix_unitaire_ht'  => $puHt,
                    'taux_tva'          => $tva,
                    'prix_unitaire_ttc' => $puTtc,
                    'total_ligne_ttc'   => $ligne,
                ];
            }

            $totalHt   = round($totalHt, 3);
            $grossTtc  = round($totalTtc, 3);
            $totalTva  = round($grossTtc - $totalHt, 3);

            // ── Remise (sur le total TTC) ───────────────────────────────────
            $remiseMontant = 0.0;
            if ($remiseType !== null && $remiseValeur !== null && $remiseValeur > 0) {
                $remiseMontant = $remiseType === 'pourcentage'
                    ? round($grossTtc * min($remiseValeur, 100) / 100, 3)
                    : round($remiseValeur, 3);
                $remiseMontant = min($remiseMontant, $grossTtc); // jamais plus que le total
            }

            $netTtc = round($grossTtc - $remiseMontant, 3);

            // ── Règlement selon le mode ─────────────────────────────────────
            $rendu        = 0.0;
            $montantRegle = $netTtc; // especes/carte : payé intégralement

            if ($modePaiement === Sale::MODE_CREDIT) {
                // Acompte optionnel ; le reste devient une créance client.
                $acompte = max(0, min((float) ($montantPaye ?? 0), $netTtc));
                $montantRegle = round($acompte, 3);
            } elseif ($modePaiement === Sale::MODE_ESPECES && $montantPaye !== null) {
                if ($montantPaye < $netTtc) {
                    throw ValidationException::withMessages([
                        'montant_paye' => "Montant payé insuffisant (total : {$netTtc} TND).",
                    ]);
                }
                $rendu = round($montantPaye - $netTtc, 3);
            }

            $sale = Sale::create([
                'user_id'        => $userId,
                'client_id'      => $clientId,
                'numero'         => $this->nextNumero(),
                'total_ht'       => $totalHt,
                'total_tva'      => $totalTva,
                'total_ttc'      => $netTtc,
                'remise_type'    => $remiseMontant > 0 ? $remiseType : null,
                'remise_valeur'  => $remiseMontant > 0 ? $remiseValeur : null,
                'remise_montant' => $remiseMontant,
                'mode_paiement'  => $modePaiement,
                'montant_paye'   => $montantPaye,
                'monnaie_rendue' => $rendu,
                'montant_regle'  => $montantRegle,
                'statut'         => Sale::STATUT_PAYEE,
                'date_vente'     => now(),
            ]);

            foreach ($lines as $line) {
                SaleItem::create([
                    'sale_id'             => $sale->id,
                    'product_id'          => $line['product']->id,
                    'designation'         => $line['designation'],
                    'quantite'            => $line['quantite'],
                    'prix_unitaire_ht'    => $line['prix_unitaire_ht'],
                    'prix_achat_unitaire' => $line['prix_achat_unitaire'],
                    'taux_tva'            => $line['taux_tva'],
                    'prix_unitaire_ttc'   => $line['prix_unitaire_ttc'],
                    'total_ligne_ttc'     => $line['total_ligne_ttc'],
                ]);

                // Decrement stock through the shared service (cross-DB safe).
                $this->stockService->createMovement(
                    productId:     $line['product']->id,
                    userId:        $userId,
                    type:          StockMovement::TYPE_SORTIE,
                    quantite:      $line['quantite'],
                    note:          "Vente caisse {$sale->numero}",
                );
            }

            return $sale;
        });
    }

    /**
     * Cancel a sale and restock every line (movement 'entree'). Idempotent-guarded.
     */
    public function cancelSale(int $saleId, int $userId): Sale
    {
        return DB::transaction(function () use ($saleId, $userId) {
            /** @var Sale $sale */
            $sale = Sale::with('items')->lockForUpdate()->findOrFail($saleId);

            if ($sale->isCancelled()) {
                throw ValidationException::withMessages([
                    'sale' => 'Cette vente est déjà annulée.',
                ]);
            }

            foreach ($sale->items as $item) {
                $this->stockService->createMovement(
                    productId: $item->product_id,
                    userId:    $userId,
                    type:      StockMovement::TYPE_ENTREE,
                    quantite:  (float) $item->quantite,
                    note:      "Annulation vente {$sale->numero}",
                );
            }

            $sale->update(['statut' => Sale::STATUT_ANNULEE]);

            return $sale;
        });
    }

    /**
     * Sequential ticket number per organisation: TKT-YYYYMMDD-NNNN.
     */
    private function nextNumero(): string
    {
        $prefix = 'TKT-' . now()->format('Ymd') . '-';

        $count = Sale::where('numero', 'like', $prefix . '%')->count();

        return $prefix . str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }
}
