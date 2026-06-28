<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Organisation;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class PublicMenuController extends Controller
{
    /**
     * Public digital menu — no authentication required.
     * Returns compose products grouped by category for the given organisation slug.
     */
    public function show(string $slug): JsonResponse
    {
        $org = Organisation::where('slug', $slug)->where('actif', true)->first();

        if (! $org) {
            return response()->json(['message' => 'Restaurant introuvable.'], 404);
        }

        if (! $org->isRestauration()) {
            return response()->json(['message' => 'Menu non disponible.'], 404);
        }

        // Bypass TenantScope — we already know which org we're querying.
        $products = Product::withoutGlobalScopes()
            ->with(['category' => fn ($q) => $q->withoutGlobalScopes()])
            ->where('organisation_id', $org->id)
            ->where('type', Product::TYPE_COMPOSE)
            ->where('actif', true)
            ->orderBy('nom')
            ->get(['id', 'nom', 'description', 'prix_vente_ht', 'taux_tva', 'category_id', 'unite_mesure']);

        // Compute prix_vente_ttc per product (accessor not available without global scope context)
        $products = $products->map(function (Product $p) {
            return [
                'id'             => $p->id,
                'nom'            => $p->nom,
                'description'    => $p->description,
                'prix_vente_ttc' => round((float) $p->prix_vente_ht * (1 + (float) $p->taux_tva / 100), 3),
                'unite'          => $p->unite_mesure,
                'categorie'      => $p->category ? ['id' => $p->category->id, 'nom' => $p->category->nom, 'couleur' => $p->category->couleur ?? '#C9A84C'] : null,
            ];
        });

        // Group by category
        $grouped = $products
            ->groupBy(fn ($p) => $p['categorie']['nom'] ?? 'Autres')
            ->map(fn ($items, $catNom) => [
                'nom'     => $catNom,
                'couleur' => $items->first()['categorie']['couleur'] ?? '#C9A84C',
                'plats'   => $items->values(),
            ])
            ->values();

        return response()->json([
            'restaurant' => [
                'nom'     => $org->nom,
                'adresse' => $org->adresse,
                'telephone' => $org->telephone,
            ],
            'categories' => $grouped,
        ]);
    }
}
