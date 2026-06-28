<?php

namespace App\Services;

use App\Helpers\UnitConversionHelper;
use App\Models\Composition;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Supplement;

class OrderService
{
    public function __construct(private StockService $stockService) {}

    public function decrementOrderStock(Order $order): void
    {
        foreach ($order->items as $item) {
            if ($item->supplement_id) {
                $supp = Supplement::with('ingredient:id,unite_mesure')->find($item->supplement_id);
                if ($supp) {
                    $this->decrementSupplement($supp, $item->quantite, $order->created_by, $order->id);
                }
            } elseif ($item->product_id) {
                $product = Product::find($item->product_id);
                if ($product) {
                    $this->decrementProduct($product, $item->quantite, $order->created_by, $order->id);
                }
            }
        }
    }

    /**
     * Check whether ingredients for the given items array are sufficient.
     * Returns an array of warnings (empty if all stock is OK).
     * Does NOT decrement any stock.
     *
     * @param array $items Each: ['product_id' => int|null, 'supplement_id' => int|null, 'quantite' => int]
     */
    public function checkIngredientWarnings(array $items): array
    {
        // Aggregate needed quantities per ingredient_id
        $needed = []; // ingredient_id => ['nom' => ..., 'unite' => ..., 'quantite' => float]

        foreach ($items as $item) {
            $qty = (int) ($item['quantite'] ?? 1);

            if (! empty($item['supplement_id'])) {
                $supp = Supplement::with('ingredient:id,nom,quantite,unite_mesure')->find($item['supplement_id']);
                if (! $supp) continue;

                $u1     = $supp->ingredient->unite_mesure ?? '';
                $u2     = $supp->unite ?? $u1;
                $factor = UnitConversionHelper::getConversionFactor($u1, $u2) ?? 1.0;
                $reqQty = round((float) $supp->quantite * $qty * $factor, 3);

                $ingId = $supp->ingredient_id;
                $needed[$ingId] = [
                    'nom'      => $supp->ingredient->nom,
                    'unite'    => $u1,
                    'quantite' => ($needed[$ingId]['quantite'] ?? 0) + $reqQty,
                    'stock'    => (float) $supp->ingredient->quantite,
                ];
            } elseif (! empty($item['product_id'])) {
                $product = Product::find($item['product_id']);
                if (! $product) continue;

                if ($product->isCompose()) {
                    $compositions = Composition::where('produit_compose_id', $product->id)
                        ->with('composant:id,nom,quantite,unite_mesure')
                        ->get();

                    foreach ($compositions as $comp) {
                        $u1     = $comp->composant->unite_mesure ?? '';
                        $u2     = $comp->unite ?? $u1;
                        $factor = UnitConversionHelper::getConversionFactor($u1, $u2) ?? 1.0;
                        $reqQty = round((float) $comp->quantite * $qty * $factor, 3);

                        $ingId = $comp->composant_id;
                        $needed[$ingId] = [
                            'nom'      => $comp->composant->nom,
                            'unite'    => $u1,
                            'quantite' => ($needed[$ingId]['quantite'] ?? 0) + $reqQty,
                            'stock'    => (float) $comp->composant->quantite,
                        ];
                    }
                }
            }
        }

        $warnings = [];
        foreach ($needed as $row) {
            if ($row['stock'] < $row['quantite']) {
                $manque = round($row['quantite'] - $row['stock'], 3);
                $warnings[] = [
                    'ingredient'          => $row['nom'],
                    'stock_actuel'        => $row['stock'] . ' ' . $row['unite'],
                    'quantite_necessaire' => $row['quantite'] . ' ' . $row['unite'],
                    'manque'              => $manque . ' ' . $row['unite'],
                ];
            }
        }

        return $warnings;
    }

    private function decrementProduct(Product $product, int $qty, int $userId, int $orderId): void
    {
        if ($product->isCompose()) {
            $compositions = Composition::where('produit_compose_id', $product->id)
                ->with('composant:id,unite_mesure')
                ->get();

            foreach ($compositions as $comp) {
                $u1     = $comp->composant->unite_mesure ?? '';
                $u2     = $comp->unite ?? $u1;
                $factor = UnitConversionHelper::getConversionFactor($u1, $u2) ?? 1.0;

                $this->stockService->createMovement(
                    productId:    $comp->composant_id,
                    userId:       $userId,
                    type:         StockMovement::TYPE_SORTIE,
                    quantite:     round((float) $comp->quantite * $qty * $factor, 3),
                    note:         "Commande #{$orderId} – {$product->nom}",
                    enforceStock: false,
                );
            }
        } else {
            $this->stockService->createMovement(
                productId: $product->id,
                userId:    $userId,
                type:      StockMovement::TYPE_SORTIE,
                quantite:  (float) $qty,
                note:      "Commande #{$orderId}",
            );
        }
    }

    private function decrementSupplement(Supplement $supp, int $qty, int $userId, int $orderId): void
    {
        $u1     = $supp->ingredient->unite_mesure ?? '';
        $u2     = $supp->unite ?? $u1;
        $factor = UnitConversionHelper::getConversionFactor($u1, $u2) ?? 1.0;

        $this->stockService->createMovement(
            productId:    $supp->ingredient_id,
            userId:       $userId,
            type:         StockMovement::TYPE_SORTIE,
            quantite:     round((float) $supp->quantite * $qty * $factor, 3),
            note:         "Supplément {$supp->nom} – Commande #{$orderId}",
            enforceStock: false,
        );
    }
}
