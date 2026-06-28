<?php

namespace App\Services;

use App\Helpers\UnitConversionHelper;
use App\Models\Composition;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\Supplement;
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
        string  $modePaiement   = Sale::MODE_ESPECES,
        ?float  $montantPaye    = null,
        ?string $remiseType     = null,
        ?float  $remiseValeur   = null,
        ?int    $clientId       = null,
        ?string $referenceCarte = null,
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

        return DB::transaction(function () use ($items, $userId, $modePaiement, $montantPaye, $remiseType, $remiseValeur, $clientId, $referenceCarte) {
            $totalHt  = 0.0;
            $totalTtc = 0.0;
            $lines    = [];

            foreach ($items as $row) {
                $quantite = (float) $row['quantite'];

                if ($quantite <= 0) {
                    throw ValidationException::withMessages(['items' => 'Quantité invalide.']);
                }

                // ── Supplément ──────────────────────────────────────────────
                if (! empty($row['supplement_id'])) {
                    /** @var Supplement $supp */
                    $supp = Supplement::with('ingredient:id,nom,unite_mesure,prix_achat_ht')
                        ->findOrFail($row['supplement_id']);

                    if (! $supp->active) {
                        throw ValidationException::withMessages([
                            'items' => "Le supplément « {$supp->nom} » est désactivé.",
                        ]);
                    }

                    $puTtc = (float) $supp->prix_vente;
                    $ligne = round($puTtc * $quantite, 3);

                    // Cost per supplement unit (ingredient dose × price × conversion factor)
                    $uniteIng  = $supp->ingredient->unite_mesure ?? '';
                    $uniteSupp = $supp->unite ?? $uniteIng;
                    $factor    = UnitConversionHelper::getConversionFactor($uniteIng, $uniteSupp) ?? 1.0;
                    $coutUnit  = round((float) $supp->ingredient->prix_achat_ht * (float) $supp->quantite * $factor, 3);

                    $totalHt  += $ligne; // restauration: TTC = HT (taux_tva = 0)
                    $totalTtc += $ligne;

                    $lines[] = [
                        'supplement'          => $supp,
                        'product_id'          => $supp->ingredient_id,
                        'designation'         => $supp->nom,
                        'prix_achat_unitaire' => $coutUnit,
                        'quantite'            => $quantite,
                        'prix_unitaire_ht'    => $puTtc,
                        'taux_tva'            => 0,
                        'prix_unitaire_ttc'   => $puTtc,
                        'total_ligne_ttc'     => $ligne,
                    ];
                    continue;
                }

                // ── Produit normal ──────────────────────────────────────────
                /** @var Product $product */
                $product = Product::lockForUpdate()->findOrFail($row['product_id']);

                // Produit composé (recette) : ses ingrédients sortent, pas son propre stock.
                if (! $product->isCompose() && $product->quantite < $quantite) {
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
                    'quantite'            => $quantite,
                    'prix_unitaire_ht'    => $puHt,
                    'taux_tva'            => $tva,
                    'prix_unitaire_ttc'   => $puTtc,
                    'total_ligne_ttc'     => $ligne,
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
                'user_id'         => $userId,
                'client_id'       => $clientId,
                'reference_carte' => $modePaiement === Sale::MODE_CARTE ? $referenceCarte : null,
                'numero'          => $this->nextNumero(),
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
                $isSupp = isset($line['supplement']);

                SaleItem::create([
                    'sale_id'             => $sale->id,
                    'product_id'          => $isSupp ? $line['product_id'] : $line['product']->id,
                    'supplement_id'       => $isSupp ? $line['supplement']->id : null,
                    'designation'         => $line['designation'],
                    'quantite'            => $line['quantite'],
                    'prix_unitaire_ht'    => $line['prix_unitaire_ht'],
                    'prix_achat_unitaire' => $line['prix_achat_unitaire'],
                    'taux_tva'            => $line['taux_tva'],
                    'prix_unitaire_ttc'   => $line['prix_unitaire_ttc'],
                    'total_ligne_ttc'     => $line['total_ligne_ttc'],
                ]);

                if ($isSupp) {
                    $this->decrementSupplementStock($line['supplement'], (float) $line['quantite'], $userId, $sale->numero);
                } else {
                    $this->decrementStock($line['product'], (float) $line['quantite'], $userId, $sale->numero);
                }
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
                if ($item->supplement_id) {
                    // Supplément : remettre la dose ingrédient avec conversion d'unité
                    $supp   = Supplement::with('ingredient:id,unite_mesure')->findOrFail($item->supplement_id);
                    $uIng   = $supp->ingredient->unite_mesure ?? '';
                    $uSupp  = $supp->unite ?? $uIng;
                    $factor = UnitConversionHelper::getConversionFactor($uIng, $uSupp) ?? 1.0;

                    $this->stockService->createMovement(
                        productId: $supp->ingredient_id,
                        userId:    $userId,
                        type:      StockMovement::TYPE_ENTREE,
                        quantite:  round((float) $supp->quantite * (float) $item->quantite * $factor, 3),
                        note:      "Annulation vente {$sale->numero} (supplément {$supp->nom})",
                    );
                } else {
                    $product = Product::findOrFail($item->product_id);

                    if ($product->isCompose()) {
                        // Plat composé : remettre chaque ingrédient via les lignes de composition
                        $compositions = Composition::where('produit_compose_id', $product->id)
                            ->with('composant:id,unite_mesure')
                            ->get();

                        foreach ($compositions as $comp) {
                            $uniteStock   = $comp->composant->unite_mesure ?? '';
                            $uniteRecette = $comp->unite ?? $uniteStock;
                            $factor       = UnitConversionHelper::getConversionFactor($uniteStock, $uniteRecette) ?? 1.0;

                            $this->stockService->createMovement(
                                productId: $comp->composant_id,
                                userId:    $userId,
                                type:      StockMovement::TYPE_ENTREE,
                                quantite:  round((float) $comp->quantite * (float) $item->quantite * $factor, 3),
                                note:      "Annulation vente {$sale->numero} (recette {$product->nom})",
                            );
                        }
                    } else {
                        // Produit simple : remettre directement
                        $this->stockService->createMovement(
                            productId: $item->product_id,
                            userId:    $userId,
                            type:      StockMovement::TYPE_ENTREE,
                            quantite:  (float) $item->quantite,
                            note:      "Annulation vente {$sale->numero}",
                        );
                    }
                }
            }

            $sale->update(['statut' => Sale::STATUT_ANNULEE]);

            return $sale;
        });
    }

    /**
     * Decrement stock for one sold product.
     * - Simple product  → one sortie movement (stock enforced).
     * - Composed product → sortie per ingredient (no stock enforcement).
     */
    private function decrementStock(Product $product, float $quantite, int $userId, string $saleNumero): void
    {
        if ($product->isCompose()) {
            $compositions = Composition::where('produit_compose_id', $product->id)
                ->with('composant:id,unite_mesure')
                ->get();

            foreach ($compositions as $comp) {
                // Convert recipe unit → stock unit before decrementing.
                // e.g. recipe uses 100 g, stock tracks kg → factor = 0.001 → deduct 0.100 kg.
                $uniteStock   = $comp->composant->unite_mesure ?? '';
                $uniteRecette = $comp->unite ?? $uniteStock;
                $factor       = UnitConversionHelper::getConversionFactor($uniteStock, $uniteRecette) ?? 1.0;

                $this->stockService->createMovement(
                    productId: $comp->composant_id,
                    userId:    $userId,
                    type:      StockMovement::TYPE_SORTIE,
                    quantite:  round((float) $comp->quantite * $quantite * $factor, 3),
                    note:      "Recette {$product->nom} — vente {$saleNumero}",
                    enforceStock: false,
                );
            }
        } else {
            $this->stockService->createMovement(
                productId: $product->id,
                userId:    $userId,
                type:      StockMovement::TYPE_SORTIE,
                quantite:  $quantite,
                note:      "Vente caisse {$saleNumero}",
            );
        }
    }

    /**
     * Decrement ingredient stock when a supplement is sold.
     * Applies the same unit conversion as the food cost calculation.
     */
    private function decrementSupplementStock(Supplement $supp, float $qtySold, int $userId, string $saleNumero): void
    {
        $uniteIng  = $supp->ingredient->unite_mesure ?? '';
        $uniteSupp = $supp->unite ?? $uniteIng;
        $factor    = UnitConversionHelper::getConversionFactor($uniteIng, $uniteSupp) ?? 1.0;

        $this->stockService->createMovement(
            productId:    $supp->ingredient_id,
            userId:       $userId,
            type:         StockMovement::TYPE_SORTIE,
            quantite:     round((float) $supp->quantite * $qtySold * $factor, 3),
            note:         "Supplément {$supp->nom} — vente {$saleNumero}",
            enforceStock: false,
        );
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
