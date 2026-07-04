<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncStockPointsCommand extends Command
{
    protected $signature   = 'points:sync-stock';
    protected $description = 'Synchronise products.quantite → stock_par_point pour le point Principal de chaque organisation (idempotent)';

    public function handle(): int
    {
        // Requêtes brutes pour contourner TenantScope (pas de middleware auth en CLI)
        $orgs = DB::table('organisations')->get();

        foreach ($orgs as $org) {
            DB::transaction(function () use ($org) {

                // 1. Trouver (ou créer) le point Principal de l'org
                $pdv = DB::table('points_de_vente')
                    ->where('organisation_id', $org->id)
                    ->where('nom', 'Principal')
                    ->first();

                if (! $pdv) {
                    // Aucun point "Principal" — prendre le premier point actif
                    $pdv = DB::table('points_de_vente')
                        ->where('organisation_id', $org->id)
                        ->where('actif', true)
                        ->orderBy('id')
                        ->first();
                }

                if (! $pdv) {
                    $this->warn("  [{$org->nom}] Aucun point de vente trouvé — ignoré");
                    return;
                }

                // 2. Produits actifs avec stock > 0
                $products = DB::table('products')
                    ->where('organisation_id', $org->id)
                    ->where('actif', true)
                    ->where('quantite', '>', 0)
                    ->get(['id', 'nom', 'quantite', 'unite_mesure']);

                $synced  = 0;
                $skipped = 0;

                foreach ($products as $product) {
                    $existing = DB::table('stock_par_point')
                        ->where('product_id', $product->id)
                        ->where('point_de_vente_id', $pdv->id)
                        ->first();

                    if ($existing) {
                        // Ligne existe déjà → mettre à jour uniquement si le stock diffère
                        if ((float) $existing->quantite !== (float) $product->quantite) {
                            DB::table('stock_par_point')
                                ->where('id', $existing->id)
                                ->update(['quantite' => $product->quantite, 'updated_at' => now()]);
                            $synced++;
                        } else {
                            $skipped++;
                        }
                    } else {
                        // Pas de ligne → créer
                        DB::table('stock_par_point')->insert([
                            'organisation_id'  => $org->id,
                            'product_id'       => $product->id,
                            'point_de_vente_id' => $pdv->id,
                            'quantite'         => $product->quantite,
                            'created_at'       => now(),
                            'updated_at'       => now(),
                        ]);
                        $synced++;
                    }
                }

                // Recalcul du stock global pour tous les produits de l'org
                // (garantit que products.quantite = SUM(stock_par_point) après l'opération)
                $allProductIds = DB::table('stock_par_point')
                    ->where('organisation_id', $org->id)
                    ->distinct()
                    ->pluck('product_id');

                foreach ($allProductIds as $pid) {
                    $sum = DB::table('stock_par_point')->where('product_id', $pid)->sum('quantite');
                    DB::table('products')->where('id', $pid)->update(['quantite' => $sum, 'updated_at' => now()]);
                }

                $this->line(sprintf(
                    '  [%s] PDV "%s" (id:%d) — %d produit(s) synchronisé(s), %d déjà à jour',
                    $org->nom, $pdv->nom, $pdv->id, $synced, $skipped
                ));
            });
        }

        $this->info('Synchronisation stock terminée.');
        return self::SUCCESS;
    }
}
