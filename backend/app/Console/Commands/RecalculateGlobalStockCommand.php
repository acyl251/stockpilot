<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecalculateGlobalStockCommand extends Command
{
    protected $signature   = 'points:recalculate-global-stock';
    protected $description = 'Recalcule products.quantite = SUM(stock_par_point) pour chaque produit (idempotent)';

    public function handle(): int
    {
        $orgs = DB::table('organisations')->get(['id', 'nom']);

        foreach ($orgs as $org) {
            // Tous les produits qui ont au moins une ligne stock_par_point
            $productsWithStock = DB::table('stock_par_point')
                ->where('organisation_id', $org->id)
                ->groupBy('product_id')
                ->select('product_id', DB::raw('SUM(quantite) as total'))
                ->get();

            $updated = 0;
            $unchanged = 0;

            foreach ($productsWithStock as $row) {
                $product = DB::table('products')
                    ->where('id', $row->product_id)
                    ->first(['id', 'quantite']);

                if (! $product) {
                    continue;
                }

                $newQty = (float) $row->total;
                $oldQty = (float) $product->quantite;

                if (abs($newQty - $oldQty) > 0.0001) {
                    DB::table('products')
                        ->where('id', $row->product_id)
                        ->update(['quantite' => $newQty, 'updated_at' => now()]);
                    $this->line(sprintf(
                        '  [%s] Produit id:%d — %.3f → %.3f',
                        $org->nom, $row->product_id, $oldQty, $newQty
                    ));
                    $updated++;
                } else {
                    $unchanged++;
                }
            }

            // Produits sans aucune ligne stock_par_point : on laisse products.quantite tel quel
            // (org sans PDV configuré — comportement mono-PDV)

            $this->line(sprintf(
                '  [%s] %d produit(s) corrigé(s), %d déjà correct(s)',
                $org->nom, $updated, $unchanged
            ));
        }

        $this->info('Recalcul terminé.');
        return self::SUCCESS;
    }
}
