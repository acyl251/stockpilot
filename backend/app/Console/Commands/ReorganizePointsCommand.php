<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReorganizePointsCommand extends Command
{
    protected $signature   = 'points:reorganize';
    protected $description = 'Renomme "Principal" → "Entrepôt" (entrepot), crée "Resto Principal" (point_vente), assigne l\'admin. Idempotent.';

    public function handle(): int
    {
        $orgs = DB::table('organisations')->get();

        foreach ($orgs as $org) {
            DB::transaction(function () use ($org) {

                // ── Idempotence : si "Entrepôt" existe déjà, tout est déjà fait ──
                $entrepot = DB::table('points_de_vente')
                    ->where('organisation_id', $org->id)
                    ->where('nom', 'Entrepôt')
                    ->where('type', 'entrepot')
                    ->first();

                if ($entrepot) {
                    $this->line("  [{$org->nom}] Déjà réorganisé — ignoré");
                    return;
                }

                // ── 1. Trouver le point "Principal" (ou le premier point) ──────
                $principal = DB::table('points_de_vente')
                    ->where('organisation_id', $org->id)
                    ->where('nom', 'Principal')
                    ->first();

                if (! $principal) {
                    $principal = DB::table('points_de_vente')
                        ->where('organisation_id', $org->id)
                        ->orderBy('id')
                        ->first();
                }

                if (! $principal) {
                    // Aucun point — créer l'entrepôt directement
                    $entrepotId = DB::table('points_de_vente')->insertGetId([
                        'organisation_id' => $org->id,
                        'nom'             => 'Entrepôt',
                        'type'            => 'entrepot',
                        'actif'           => true,
                        'created_at'      => now(),
                        'updated_at'      => now(),
                    ]);
                    $this->line("  [{$org->nom}] Entrepôt créé (id: {$entrepotId}) — aucun point existant");
                } else {
                    // ── 2. Renommer en "Entrepôt" et changer le type ─────────────
                    DB::table('points_de_vente')
                        ->where('id', $principal->id)
                        ->update(['nom' => 'Entrepôt', 'type' => 'entrepot', 'updated_at' => now()]);
                    $entrepotId = $principal->id;
                    $this->line("  [{$org->nom}] PDV id:{$principal->id} renommé → Entrepôt (type: entrepot)");
                }

                // ── 3. Mettre TOUT le stock dans stock_par_point de l'Entrepôt ──
                $products = DB::table('products')
                    ->where('organisation_id', $org->id)
                    ->where('actif', true)
                    ->where('quantite', '>', 0)
                    ->get(['id', 'quantite']);

                $synced = 0;
                foreach ($products as $product) {
                    $existing = DB::table('stock_par_point')
                        ->where('product_id', $product->id)
                        ->where('point_de_vente_id', $entrepotId)
                        ->first();

                    if ($existing) {
                        DB::table('stock_par_point')
                            ->where('id', $existing->id)
                            ->update(['quantite' => $product->quantite, 'updated_at' => now()]);
                    } else {
                        DB::table('stock_par_point')->insert([
                            'organisation_id'   => $org->id,
                            'product_id'        => $product->id,
                            'point_de_vente_id' => $entrepotId,
                            'quantite'          => $product->quantite,
                            'created_at'        => now(),
                            'updated_at'        => now(),
                        ]);
                    }
                    $synced++;
                }
                $this->line("     {$synced} produit(s) stockés dans l'Entrepôt");

                // Recalcul products.quantite = SUM(stock_par_point) pour tous les produits affectés
                $allProductIds = DB::table('stock_par_point')
                    ->where('organisation_id', $org->id)
                    ->distinct()
                    ->pluck('product_id');

                foreach ($allProductIds as $pid) {
                    $sum = DB::table('stock_par_point')->where('product_id', $pid)->sum('quantite');
                    DB::table('products')->where('id', $pid)->update(['quantite' => $sum, 'updated_at' => now()]);
                }

                // ── 4. Créer "Resto Principal" (type point_vente) ───────────────
                $restoPrincipal = DB::table('points_de_vente')
                    ->where('organisation_id', $org->id)
                    ->where('nom', 'Resto Principal')
                    ->first();

                if (! $restoPrincipal) {
                    $restoId = DB::table('points_de_vente')->insertGetId([
                        'organisation_id' => $org->id,
                        'nom'             => 'Resto Principal',
                        'type'            => 'point_vente',
                        'actif'           => true,
                        'created_at'      => now(),
                        'updated_at'      => now(),
                    ]);
                    $this->line("     Resto Principal créé (id: {$restoId})");
                } else {
                    $restoId = $restoPrincipal->id;
                    $this->line("     Resto Principal existe déjà (id: {$restoId})");
                }

                // ── 5. Rattacher l'admin de l'org à "Resto Principal" ──────────
                $admin = DB::table('users')
                    ->where('organisation_id', $org->id)
                    ->whereIn('role', ['admin', 'super_admin'])
                    ->orderBy('id')
                    ->first();

                if ($admin) {
                    DB::table('users')
                        ->where('id', $admin->id)
                        ->update(['point_de_vente_id' => $restoId, 'updated_at' => now()]);
                    $this->line("     Admin id:{$admin->id} rattaché à Resto Principal");
                } else {
                    $this->warn("     Aucun admin trouvé pour {$org->nom}");
                }
            });
        }

        $this->info('Réorganisation terminée.');
        return self::SUCCESS;
    }
}
