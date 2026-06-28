<?php

namespace App\Console\Commands;

use App\Models\Organisation;
use App\Services\RestaurantCategoryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedRestaurantCategories extends Command
{
    protected $signature = 'restaurants:seed-categories {--org= : ID d\'organisation spécifique}';
    protected $description = 'Crée les catégories de base pour les organisations restauration';

    public function handle(RestaurantCategoryService $service): int
    {
        $orgId = $this->option('org');

        if ($orgId) {
            $orgs = Organisation::withoutGlobalScopes()->where('id', $orgId)->get();
        } else {
            $orgs = Organisation::withoutGlobalScopes()->where('secteur', 'restauration')->get();
        }

        if ($orgs->isEmpty()) {
            $this->warn('Aucune organisation restauration trouvée.');

            // Debug: affiche toutes les orgs et leur secteur
            $all = DB::table('organisations')->select('id', 'nom', 'secteur')->get();
            $this->table(['ID', 'Nom', 'Secteur'], $all->map(fn($o) => [$o->id, $o->nom, $o->secteur ?? 'NULL']));
            return 1;
        }

        foreach ($orgs as $org) {
            $before = DB::table('categories')->where('organisation_id', $org->id)->count();
            $service->seedForOrganisation($org->id);
            $after  = DB::table('categories')->where('organisation_id', $org->id)->count();
            $added  = $after - $before;

            $this->info("Org #{$org->id} « {$org->nom} » (secteur: {$org->secteur}) — {$added} catégorie(s) ajoutée(s) ({$after} total)");
        }

        // Affiche le résultat
        foreach ($orgs as $org) {
            $cats = DB::table('categories')->where('organisation_id', $org->id)->select('nom', 'couleur')->get();
            $this->line("\nCatégories de « {$org->nom} » :");
            $this->table(['Nom', 'Couleur'], $cats->map(fn($c) => [$c->nom, $c->couleur]));
        }

        return 0;
    }
}
