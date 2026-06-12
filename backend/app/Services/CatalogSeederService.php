<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Organisation;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\TypeAttribute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CatalogSeederService
{
    public function __construct(private AIService $ai) {}

    /**
     * Ask AI for a full starter catalog of the given sector (types + categories + products),
     * then create all of it in the org's catalog and mark onboarding as complete.
     *
     * @return array{types:int, categories:int, products:int} counts created (zeros if AI unavailable)
     */
    public function seedFromSector(int $orgId, string $secteur): array
    {
        $empty = ['types' => 0, 'categories' => 0, 'products' => 0];

        // Bind the new org context BEFORE the AI call so auto-injected organisation_id
        // and the AI cache key both target the new tenant.
        app()->bind('current_organisation_id', fn () => $orgId);

        try {
            $catalog = $this->ai->suggestFullCatalog($secteur);

            $types      = $catalog['types']      ?? [];
            $categories = $catalog['categories'] ?? [];
            $products   = $catalog['products']   ?? [];

            // Graceful degradation: if the AI is unavailable (missing/invalid key
            // or API down) it returns empty arrays — fall back to a curated
            // sector catalog so the org never starts with an empty catalogue.
            if (empty($products)) {
                Log::info("CatalogSeeder: AI returned no products for org {$orgId}, using offline fallback for sector '{$secteur}'.");
                $catalog    = CatalogFallback::forSector($secteur);
                $types      = $catalog['types']      ?? [];
                $categories = $catalog['categories'] ?? [];
                $products   = $catalog['products']   ?? [];
            }

            if (empty($types) && empty($categories) && empty($products)) {
                return $empty;
            }

            return DB::transaction(function () use ($orgId, $secteur, $types, $categories, $products) {
                $categoryCache = [];

                $nbCategories = $this->createCategories($orgId, $categories, $categoryCache);
                $nbTypes      = $this->createTypes($types);
                $nbProducts   = $this->createProducts($orgId, $products, $categoryCache);
                // createProducts may add missing categories referenced by products
                $nbCategories = count($categoryCache);

                if ($nbProducts > 0) {
                    Organisation::withoutGlobalScopes()
                        ->where('id', $orgId)
                        ->update([
                            'onboarding_complete'     => true,
                            'ia_catalog_seeded_count' => $nbProducts,
                            'ia_catalog_seeded_at'    => now(),
                        ]);
                }

                return [
                    'types'      => $nbTypes,
                    'categories' => $nbCategories,
                    'products'   => $nbProducts,
                ];
            });
        } catch (\Throwable $e) {
            Log::warning("CatalogSeeder failed for org {$orgId}: " . $e->getMessage());
            return $empty;
        }
    }

    /** Create categories from the explicit "categories" list. Fills $cache (nom → id). */
    private function createCategories(int $orgId, array $categories, array &$cache): int
    {
        foreach ($categories as $nom) {
            if (!is_string($nom) || trim($nom) === '') {
                continue;
            }
            $this->resolveCategory($orgId, $nom, $cache);
        }

        return count($cache);
    }

    /** Create product types with their attributes. */
    private function createTypes(array $types): int
    {
        $count = 0;

        foreach ($types as $typeData) {
            if (empty($typeData['nom'])) {
                continue;
            }

            $type = ProductType::create([
                'nom'            => $typeData['nom'],
                'icone'          => $typeData['icone']       ?? null,
                'description'    => $typeData['description'] ?? null,
                'suggere_par_ia' => true,
                'actif'          => true,
            ]);

            foreach ($typeData['attributs'] ?? [] as $i => $attr) {
                if (empty($attr['nom']) || empty($attr['label']) || empty($attr['type_donnee'])) {
                    continue;
                }

                $options = $attr['options_select'] ?? null;
                if (is_string($options)) {
                    $options = array_values(array_filter(array_map('trim', explode(',', $options))));
                }

                TypeAttribute::create([
                    'product_type_id' => $type->id,
                    'nom'             => $attr['nom'],
                    'label'           => $attr['label'],
                    'type_donnee'     => $attr['type_donnee'],
                    'obligatoire'     => (bool) ($attr['obligatoire'] ?? false),
                    'options_select'  => $attr['type_donnee'] === 'select' ? $options : null,
                    'ordre'           => $attr['ordre'] ?? $i,
                ]);
            }

            $count++;
        }

        return $count;
    }

    /** Create products, creating any referenced category on the fly. */
    private function createProducts(int $orgId, array $products, array &$cache): int
    {
        $count = 0;

        foreach ($products as $p) {
            if (empty($p['nom'])) {
                continue;
            }

            $catId = $this->resolveCategory($orgId, $p['categorie'] ?? 'Général', $cache);

            $ref = !empty($p['reference'])
                ? (string) $p['reference']
                : Str::upper(Str::substr(Str::slug($p['nom']), 0, 5)) . '-' . rand(100, 999);

            if (Product::where('reference', $ref)->exists()) {
                $ref .= '-' . rand(10, 99);
            }

            Product::create([
                'nom'           => $p['nom'],
                'reference'     => $ref,
                'description'   => $p['description'] ?? null,
                'category_id'   => $catId,
                'unite_mesure'  => $p['unite_mesure']  ?? 'pcs',
                'quantite'      => (int)   ($p['quantite']      ?? 0),
                'seuil_alerte'  => (int)   ($p['seuil_alerte']  ?? 5),
                'prix_achat_ht' => (float) ($p['prix_achat_ht'] ?? 0),
                'prix_vente_ht' => (float) ($p['prix_vente_ht'] ?? 0),
                'taux_tva'      => (int)   ($p['taux_tva']      ?? 19),
                'actif'         => true,
            ]);

            $count++;
        }

        return $count;
    }

    /** Get-or-create a category by name, memoized in $cache. Returns its id. */
    private function resolveCategory(int $orgId, string $nom, array &$cache): int
    {
        $nom = trim($nom) !== '' ? trim($nom) : 'Général';

        if (!isset($cache[$nom])) {
            $cat = Category::firstOrCreate(
                ['nom' => $nom, 'organisation_id' => $orgId],
                ['couleur' => $this->randomColor(), 'actif' => true]
            );
            $cache[$nom] = $cat->id;
        }

        return $cache[$nom];
    }

    private function randomColor(): string
    {
        $colors = ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ef4444', '#06b6d4', '#84cc16', '#f97316'];
        return $colors[array_rand($colors)];
    }
}
