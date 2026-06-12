<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockService
{
    /**
     * Create a stock movement and let the Oracle trigger update the product quantity.
     */
    public function createMovement(
        int     $productId,
        int     $userId,
        string  $type,
        float   $quantite,
        ?string $note          = null,
        ?string $dateMouvement = null,
    ): StockMovement {
        $product = Product::lockForUpdate()->findOrFail($productId);

        if ($type === StockMovement::TYPE_SORTIE && $product->quantite < $quantite) {
            throw ValidationException::withMessages([
                'quantite' => "Stock insuffisant. Disponible: {$product->quantite} {$product->unite_mesure}.",
            ]);
        }

        $quantiteApres = match ($type) {
            StockMovement::TYPE_ENTREE => $product->quantite + $quantite,
            StockMovement::TYPE_SORTIE => max(0, $product->quantite - $quantite),
            StockMovement::TYPE_AJUST  => $quantite,
        };

        return DB::transaction(function () use (
            $product, $userId, $type, $quantite, $quantiteApres, $note, $dateMouvement
        ) {
            $movement = StockMovement::create([
                'product_id'     => $product->id,
                'user_id'        => $userId,
                'type_mouvement' => $type,
                'quantite'       => $quantite,
                'quantite_avant' => $product->quantite,
                'quantite_apres' => $quantiteApres,
                'note'           => $note,
                'date_mouvement' => $dateMouvement ?? now(),
            ]);

            // On Oracle, trg_update_stock handles this at DB level.
            // On other drivers (SQLite for local dev), update in PHP.
            if (DB::connection()->getDriverName() !== 'oracle') {
                $product->updateQuietly(['quantite' => $quantiteApres]);
            }

            return $movement;
        });
    }

    /**
     * Get products currently below alert threshold.
     */
    public function getAlertProducts(): \Illuminate\Database\Eloquent\Collection
    {
        return Product::whereRaw('quantite <= seuil_alerte')
            ->where('actif', true)
            ->with('category')
            ->orderBy('quantite')
            ->get();
    }

    /**
     * Get stock valuation by category.
     */
    public function getStockValuation(): array
    {
        return Product::where('actif', true)
            ->select([
                'category_id',
                DB::raw('SUM(quantite * prix_achat_ht) as valeur_ht'),
                DB::raw('SUM(quantite * prix_vente_ht) as valeur_vente'),
                DB::raw('COUNT(*) as nb_produits'),
            ])
            ->groupBy('category_id')
            ->with('category:id,nom,couleur')
            ->get()
            ->toArray();
    }
}
