<?php

namespace App\Console\Commands;

use App\Models\Organisation;
use App\Services\CatalogSeederService;
use Illuminate\Console\Command;

class SeedCatalogCommand extends Command
{
    protected $signature = 'catalog:seed {org : Organisation id} {--secteur= : Override the sector}';

    protected $description = "Génère le catalogue de démarrage (types + catégories + produits) d'une organisation via l'IA";

    public function handle(CatalogSeederService $seeder): int
    {
        $org = Organisation::withoutGlobalScopes()->with('plan')->find($this->argument('org'));

        if (! $org) {
            $this->error("Organisation #{$this->argument('org')} introuvable.");
            return self::FAILURE;
        }

        if (! $org->hasAIEnabled()) {
            $this->warn("Le plan de « {$org->nom} » n'a pas l'IA activée.");
            return self::FAILURE;
        }

        $secteur = $this->option('secteur') ?: $org->secteur;

        if (! $secteur) {
            $this->error("Aucun secteur défini pour « {$org->nom} ». Utilisez --secteur=...");
            return self::FAILURE;
        }

        $this->info("Génération du catalogue IA pour « {$org->nom} » (secteur : {$secteur})…");

        $counts = $seeder->seedFromSector($org->id, $secteur);

        $this->table(
            ['Types', 'Catégories', 'Produits'],
            [[$counts['types'], $counts['categories'], $counts['products']]]
        );

        if ($counts['products'] === 0) {
            $this->warn("Aucun produit créé — l'IA est peut-être indisponible (vérifiez OPENAI_API_KEY).");
            return self::FAILURE;
        }

        $this->info("Catalogue généré avec succès.");
        return self::SUCCESS;
    }
}
