<?php

namespace App\Services;

use App\Models\Product;
use App\Models\PointDeVente;
use App\Models\StockMovement;
use App\Models\StockParPoint;
use App\Models\Transfert;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransfertService
{
    /**
     * Execute an atomic multi-product transfer from one PDV to another.
     *
     * @param int   $sourceId  point_de_vente source
     * @param int   $destId    point_de_vente destination
     * @param array $items     [['product_id' => int, 'quantite' => float], ...]
     * @param int   $userId    ID of the admin performing the transfer
     * @param string|null $note optional note
     */
    public function execute(
        int     $sourceId,
        int     $destId,
        array   $items,
        int     $userId,
        ?string $note = null,
    ): Transfert {
        if ($sourceId === $destId) {
            throw ValidationException::withMessages([
                'point_dest_id' => 'La source et la destination doivent être différentes.',
            ]);
        }

        $source = PointDeVente::findOrFail($sourceId);
        $dest   = PointDeVente::findOrFail($destId);

        return DB::transaction(function () use ($source, $dest, $items, $userId, $note) {
            $transfert = Transfert::create([
                'point_source_id' => $source->id,
                'point_dest_id'   => $dest->id,
                'created_by'      => $userId,
                'note'            => $note,
            ]);

            foreach ($items as $item) {
                $productId = (int) $item['product_id'];
                $quantite  = (float) $item['quantite'];

                $product = Product::lockForUpdate()->findOrFail($productId);

                // Lock source stock row
                $stockSource = StockParPoint::lockForUpdate()
                    ->where('product_id', $productId)
                    ->where('point_de_vente_id', $source->id)
                    ->first();

                $stockSourceQte = $stockSource ? (float) $stockSource->quantite : 0.0;

                if ($stockSourceQte < $quantite) {
                    throw ValidationException::withMessages([
                        'items' => "Stock insuffisant pour « {$product->nom} » sur « {$source->nom} » "
                            . "(disponible : {$stockSourceQte} {$product->unite_mesure}, demandé : {$quantite}).",
                    ]);
                }

                // Decrement source
                if ($stockSource) {
                    $stockSource->update(['quantite' => $stockSourceQte - $quantite]);
                }

                // Increment destination (create row if absent)
                $stockDest = StockParPoint::lockForUpdate()
                    ->where('product_id', $productId)
                    ->where('point_de_vente_id', $dest->id)
                    ->first();

                if ($stockDest) {
                    $stockDest->update(['quantite' => (float) $stockDest->quantite + $quantite]);
                } else {
                    StockParPoint::create([
                        'organisation_id'   => app('current_organisation_id'),
                        'product_id'        => $productId,
                        'point_de_vente_id' => $dest->id,
                        'quantite'          => $quantite,
                    ]);
                }

                // Recalcul du stock global pour rester synchronisé avec SUM(stock_par_point)
                // Même si decrement + increment s'annulent en théorie, on recalcule pour corriger
                // toute désynchronisation préexistante sur ce produit.
                $stockGlobal = DB::table('stock_par_point')
                    ->where('product_id', $productId)
                    ->sum('quantite');
                $product->updateQuietly(['quantite' => $stockGlobal]);

                $unite = $product->unite_mesure ?? '';

                // Mouvement sortie sur la source
                StockMovement::create([
                    'product_id'        => $productId,
                    'user_id'           => $userId,
                    'point_de_vente_id' => $source->id,
                    'type_mouvement'    => StockMovement::TYPE_SORTIE,
                    'quantite'          => $quantite,
                    'quantite_avant'    => $stockSourceQte,
                    'quantite_apres'    => $stockSourceQte - $quantite,
                    'note'              => "Transfert vers « {$dest->nom} » — {$quantite} {$unite} {$product->nom}",
                    'date_mouvement'    => now(),
                ]);

                // Mouvement entrée sur la destination
                $stockDestBefore = $stockDest ? (float) $stockDest->quantite : 0.0;
                StockMovement::create([
                    'product_id'        => $productId,
                    'user_id'           => $userId,
                    'point_de_vente_id' => $dest->id,
                    'type_mouvement'    => StockMovement::TYPE_ENTREE,
                    'quantite'          => $quantite,
                    'quantite_avant'    => $stockDestBefore,
                    'quantite_apres'    => $stockDestBefore + $quantite,
                    'note'              => "Transfert depuis « {$source->nom} » — {$quantite} {$unite} {$product->nom}",
                    'date_mouvement'    => now(),
                ]);

                // Save transfert item
                $transfert->items()->create([
                    'product_id' => $productId,
                    'quantite'   => $quantite,
                    'unite'      => $unite,
                ]);
            }

            return $transfert->load(['pointSource', 'pointDest', 'createdBy:id,nom,prenom', 'items.product:id,nom,unite_mesure']);
        });
    }
}
