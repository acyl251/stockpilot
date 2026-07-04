<?php

namespace App\Console\Commands;

use App\Models\Organisation;
use App\Models\PointDeVente;
use App\Models\Product;
use App\Models\StockParPoint;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateExistingPointsCommand extends Command
{
    protected $signature   = 'points:migrate-existing';
    protected $description = 'Crée un PDV "Principal" par organisation et migre le stock existant (idempotent)';

    public function handle(): int
    {
        $orgs = Organisation::all();

        foreach ($orgs as $org) {
            DB::transaction(function () use ($org) {
                // Crée le PDV "Principal" s'il n'existe pas encore
                $pdv = PointDeVente::firstOrCreate(
                    ['organisation_id' => $org->id, 'nom' => 'Principal'],
                    ['type' => 'point_vente', 'actif' => true],
                );

                if ($pdv->wasRecentlyCreated) {
                    $this->line("  → [{$org->nom}] PDV «Principal» créé (id: {$pdv->id})");
                } else {
                    $this->line("  → [{$org->nom}] PDV «Principal» existe déjà (id: {$pdv->id})");
                }

                // Migre le stock de chaque produit vers stock_par_point
                $products = Product::where('organisation_id', $org->id)->get();
                $migrated = 0;

                foreach ($products as $product) {
                    $exists = StockParPoint::where('product_id', $product->id)
                        ->where('point_de_vente_id', $pdv->id)
                        ->exists();

                    if (! $exists) {
                        StockParPoint::create([
                            'organisation_id'  => $org->id,
                            'product_id'       => $product->id,
                            'point_de_vente_id' => $pdv->id,
                            'quantite'         => $product->quantite,
                        ]);
                        $migrated++;
                    }
                }

                if ($migrated > 0) {
                    $this->line("     {$migrated} produit(s) migrés vers stock_par_point");
                }
            });
        }

        $this->info('Migration points de vente terminée.');

        return self::SUCCESS;
    }
}
