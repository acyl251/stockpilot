<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class RestaurantCategoryService
{
    private const MENU_CATEGORIES = [
        ['nom' => 'Burgers',       'couleur' => '#F97316', 'description' => 'Burgers et sandwichs chauds', 'actif' => 1],
        ['nom' => 'Sandwichs',     'couleur' => '#FBBF24', 'description' => 'Sandwichs froids et paninis', 'actif' => 1],
        ['nom' => 'Pizzas',        'couleur' => '#EF4444', 'description' => 'Pizzas et calzones',          'actif' => 1],
        ['nom' => 'Plats chauds',  'couleur' => '#8B5CF6', 'description' => 'Plats du jour et plats chauds', 'actif' => 1],
        ['nom' => 'Desserts',      'couleur' => '#EC4899', 'description' => 'Desserts et pâtisseries',     'actif' => 1],
        ['nom' => 'Boissons',      'couleur' => '#3B82F6', 'description' => 'Boissons au menu',            'actif' => 1],
    ];

    private const INGREDIENT_CATEGORIES = [
        ['nom' => 'Viandes & poissons',  'couleur' => '#DC2626', 'description' => 'Viandes, poissons et fruits de mer', 'actif' => 1],
        ['nom' => 'Légumes & fruits',    'couleur' => '#16A34A', 'description' => 'Légumes frais, fruits et crudités',  'actif' => 1],
        ['nom' => 'Produits laitiers',   'couleur' => '#0891B2', 'description' => 'Fromages, crèmes et produits laitiers', 'actif' => 1],
        ['nom' => 'Épicerie',            'couleur' => '#CA8A04', 'description' => 'Épices, condiments et produits secs',  'actif' => 1],
        ['nom' => 'Boissons (matières)', 'couleur' => '#7C3AED', 'description' => 'Matières premières pour boissons',   'actif' => 1],
    ];

    /**
     * Seed base restauration categories for an organisation.
     * Safe to call multiple times — skips categories whose name already exists.
     */
    public function seedForOrganisation(int $orgId): void
    {
        $existing = DB::table('categories')
            ->where('organisation_id', $orgId)
            ->pluck('nom')
            ->map(fn($n) => mb_strtolower($n))
            ->all();

        $now = now();
        $rows = [];

        foreach ([...self::MENU_CATEGORIES, ...self::INGREDIENT_CATEGORIES] as $cat) {
            if (in_array(mb_strtolower($cat['nom']), $existing, strict: true)) {
                continue;
            }
            $rows[] = [
                ...$cat,
                'organisation_id' => $orgId,
                'created_at'      => $now,
                'updated_at'      => $now,
            ];
        }

        if (!empty($rows)) {
            DB::table('categories')->insert($rows);
        }
    }
}
