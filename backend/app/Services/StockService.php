<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockParPoint;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockService
{
    public function createMovement(
        int     $productId,
        int     $userId,
        string  $type,
        float   $quantite,
        ?string $note           = null,
        ?string $dateMouvement  = null,
        bool    $enforceStock   = true,
        ?int    $pointDeVenteId = null,
    ): StockMovement {
        $product = Product::lockForUpdate()->findOrFail($productId);

        if ($pointDeVenteId !== null) {
            return $this->createMovementWithPoint(
                $product, $userId, $type, $quantite, $note, $dateMouvement, $enforceStock, $pointDeVenteId
            );
        }

        // ── Mode global (pas de PDV) — comportement original ──────────────
        if ($enforceStock && $type === StockMovement::TYPE_SORTIE && $product->quantite < $quantite) {
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

            if (DB::connection()->getDriverName() !== 'oracle') {
                $product->updateQuietly(['quantite' => $quantiteApres]);
            }

            return $movement;
        });
    }

    private function createMovementWithPoint(
        Product $product,
        int     $userId,
        string  $type,
        float   $quantite,
        ?string $note,
        ?string $dateMouvement,
        bool    $enforceStock,
        int     $pointDeVenteId,
    ): StockMovement {
        return DB::transaction(function () use (
            $product, $userId, $type, $quantite, $note, $dateMouvement, $enforceStock, $pointDeVenteId
        ) {
            $stockPoint = StockParPoint::lockForUpdate()
                ->firstOrCreate(
                    ['product_id' => $product->id, 'point_de_vente_id' => $pointDeVenteId],
                    ['organisation_id' => app('current_organisation_id'), 'quantite' => 0],
                );

            $qtePoint = (float) $stockPoint->quantite;

            if ($enforceStock && $type === StockMovement::TYPE_SORTIE && $qtePoint < $quantite) {
                throw ValidationException::withMessages([
                    'quantite' => "Stock insuffisant sur ce point de vente. Disponible: {$qtePoint} {$product->unite_mesure}.",
                ]);
            }

            $qtePointApres = match ($type) {
                StockMovement::TYPE_ENTREE => $qtePoint + $quantite,
                StockMovement::TYPE_SORTIE => max(0, $qtePoint - $quantite),
                StockMovement::TYPE_AJUST  => $quantite,
            };

            $stockPoint->updateQuietly(['quantite' => $qtePointApres]);

            // Recalcul du stock global = SUM de tous les points
            $stockGlobal = StockParPoint::where('product_id', $product->id)->sum('quantite');
            $product->updateQuietly(['quantite' => $stockGlobal]);

            $movement = StockMovement::create([
                'product_id'       => $product->id,
                'user_id'          => $userId,
                'point_de_vente_id' => $pointDeVenteId,
                'type_mouvement'   => $type,
                'quantite'         => $quantite,
                'quantite_avant'   => $product->quantite,
                'quantite_apres'   => $stockGlobal,
                'note'             => $note,
                'date_mouvement'   => $dateMouvement ?? now(),
            ]);

            return $movement;
        });
    }

    public function getAlertProducts(): \Illuminate\Database\Eloquent\Collection
    {
        return Product::whereRaw('quantite <= seuil_alerte')
            ->where('actif', true)
            ->where('type', '!=', 'compose')
            ->with('category')
            ->orderBy('quantite')
            ->get();
    }

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
