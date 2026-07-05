<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Organisation;
use App\Models\Product;
use App\Models\StockParPoint;
use App\Services\ActivityLogService;
use App\Services\PlanLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user  = app('current_user');

        // Resolve which PDV context to use for stock display
        $pdvId = null;
        if ($user->role === 'operateur' && $user->point_de_vente_id) {
            $pdvId = (int) $user->point_de_vente_id;
        } elseif ($user->role === 'admin' && $request->point_de_vente_id) {
            $pdvId = (int) $request->point_de_vente_id;
        }

        $query = Product::with(['category', 'productType'])
            ->when($request->search, fn($q, $s) => $q->where(
                fn($w) => $w->where('products.nom', 'LIKE', "%$s%")->orWhere('products.reference', 'LIKE', "%$s%")
            ))
            ->when($request->category_id, fn($q, $id) => $q->where('products.category_id', $id))
            ->when($request->product_type_id, fn($q, $id) => $q->where('products.product_type_id', $id))
            ->when($request->type, fn($q, $t) => $q->where('products.type', $t))
            ->when(
                $request->has('actif'),
                fn($q) => $q->where('products.actif', $request->boolean('actif')),
                fn($q) => $q->where('products.actif', true)
            )
            ->orderBy('products.nom');

        // Status filter: use per-PDV stock when a PDV context is active
        if ($pdvId !== null) {
            if ($request->statut === 'alerte') {
                $query->whereExists(fn($sub) => $sub
                    ->from('stock_par_point')
                    ->whereColumn('stock_par_point.product_id', 'products.id')
                    ->where('stock_par_point.point_de_vente_id', $pdvId)
                    ->whereRaw('stock_par_point.quantite > 0')
                    ->whereRaw('stock_par_point.quantite <= products.seuil_alerte')
                );
            } elseif ($request->statut === 'rupture') {
                // Rupture = no stock_par_point row, or row with quantite <= 0
                $query->where(fn($w) => $w
                    ->whereNotExists(fn($sub) => $sub
                        ->from('stock_par_point')
                        ->whereColumn('stock_par_point.product_id', 'products.id')
                        ->where('stock_par_point.point_de_vente_id', $pdvId)
                        ->where('stock_par_point.quantite', '>', 0)
                    )
                );
            }
        } else {
            $query->when($request->statut === 'alerte', fn($q) => $q->whereRaw(
                'quantite > 0 AND quantite <= seuil_alerte'
            ))
            ->when($request->statut === 'rupture', fn($q) => $q->where('quantite', '<=', 0));
        }

        $paginated = $query->paginate($request->per_page ?? 20);

        // Override quantite with per-PDV stock so the resource reflects the right value
        if ($pdvId !== null) {
            $stockMap = StockParPoint::where('point_de_vente_id', $pdvId)
                ->whereIn('product_id', $paginated->pluck('id'))
                ->pluck('quantite', 'product_id');

            $paginated->each(function (Product $product) use ($stockMap) {
                $product->quantite = (float) ($stockMap[$product->id] ?? 0.0);
            });
        }

        return ProductResource::collection($paginated);
    }

    /** Check whether a product reference is still available within the tenant. */
    public function checkReference(Request $request): JsonResponse
    {
        $request->validate([
            'reference'  => 'required|string|max:100',
            'exclude_id' => 'nullable|integer',
        ]);

        $exists = Product::where('reference', $request->reference)
            ->where('actif', true)
            ->when($request->exclude_id, fn($q, $id) => $q->where('id', '!=', $id))
            ->exists();

        return response()->json(['available' => ! $exists]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($this->isRestrictedOperateur()) {
            return response()->json(['message' => 'Action non autorisée dans une organisation multi-points de vente.'], 403);
        }

        $org = Organisation::with('plan')->findOrFail(app('current_organisation_id'));

        if (!PlanLimitService::check('produits', $org)) {
            return response()->json(PlanLimitService::limitResponse('produits', $org), 403);
        }

        $isIngredient = $org->isRestauration() && ($request->input('type', 'simple') === Product::TYPE_SIMPLE);

        $validated = $request->validate([
            'category_id'     => 'nullable|integer',
            'product_type_id' => 'nullable|integer',
            'nom'             => 'required|string|max:255',
            'reference'       => ['nullable', 'numeric', 'digits_between:1,20',
                                   Rule::unique('products', 'reference')
                                       ->where('actif', true)
                                       ->where('organisation_id', app('current_organisation_id'))],
            'description'     => 'nullable|string|max:1000',
            'seuil_alerte'    => 'required|numeric|min:0',
            'unite_mesure'    => 'nullable|string|max:30',
            'prix_achat_ht'   => 'required|numeric|min:0',
            'taux_tva'        => 'nullable|numeric|min:0|max:100',
            'prix_vente_ht'   => $isIngredient ? 'nullable|numeric|min:0' : 'required|numeric|min:0',
            'type'            => 'nullable|in:simple,compose',
            'attributs'       => 'nullable|array',
        ]);

        // Null prix_vente stored as 0 so margin / CA calculations never receive null
        $validated['prix_vente_ht'] ??= 0;

        if (($validated['type'] ?? 'simple') === Product::TYPE_COMPOSE) {
            if (! $org->isRestauration()) {
                return response()->json(['message' => 'Les produits composés nécessitent le secteur restauration.'], 403);
            }
        }

        $product = Product::create($validated);

        if (empty($product->reference)) {
            $product->update(['reference' => $this->nextReference($product->organisation_id)]);
        }

        ActivityLogService::log('created', 'produit',
            "Produit '{$product->nom}' créé — prix " . number_format((float) $product->prix_vente_ht, 3, '.', '') . " TND"
        );

        return response()->json(new ProductResource($product->fresh(['category', 'productType'])), 201);
    }

    public function show(int $id): JsonResponse
    {
        $product = Product::with([
            'category', 'productType',
            'composition.composant',
            'stockMovements' => fn($q) => $q->latest()->limit(10),
        ])->findOrFail($id);

        return response()->json(new ProductResource($product));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if ($this->isRestrictedOperateur()) {
            return response()->json(['message' => 'Action non autorisée dans une organisation multi-points de vente.'], 403);
        }

        $product      = Product::findOrFail($id);
        $org          = Organisation::findOrFail(app('current_organisation_id'));
        $effectiveType = $request->input('type', $product->type ?? Product::TYPE_SIMPLE);
        $isIngredient  = $org->isRestauration() && ($effectiveType === Product::TYPE_SIMPLE);

        $validated = $request->validate([
            'category_id'     => 'nullable|integer',
            'product_type_id' => 'nullable|integer',
            'nom'             => 'sometimes|required|string|max:255',
            'reference'       => ['nullable', 'numeric', 'digits_between:1,20',
                                   Rule::unique('products', 'reference')
                                       ->where('actif', true)
                                       ->where('organisation_id', app('current_organisation_id'))
                                       ->ignore($product->id)],
            'description'     => 'nullable|string|max:1000',
            'seuil_alerte'    => 'sometimes|required|numeric|min:0',
            'unite_mesure'    => 'nullable|string|max:30',
            'prix_achat_ht'   => 'sometimes|required|numeric|min:0',
            'taux_tva'        => 'nullable|numeric|min:0|max:100',
            'prix_vente_ht'   => $isIngredient ? 'nullable|numeric|min:0' : 'sometimes|required|numeric|min:0',
            'type'            => 'nullable|in:simple,compose',
            'attributs'       => 'nullable|array',
            'actif'           => 'nullable|boolean',
        ]);

        // Null prix_vente stored as 0 so margin / CA calculations never receive null
        if (array_key_exists('prix_vente_ht', $validated)) {
            $validated['prix_vente_ht'] ??= 0;
        }

        if (($validated['type'] ?? $product->type) === Product::TYPE_COMPOSE) {
            if (! $org->isRestauration()) {
                return response()->json(['message' => 'Les produits composés nécessitent le secteur restauration.'], 403);
            }
        }

        $old = $product->only(['nom', 'prix_vente_ht', 'prix_achat_ht', 'seuil_alerte', 'quantite']);
        $product->update($validated);

        ActivityLogService::log('updated', 'produit',
            "Produit '{$product->nom}' modifié",
            ['avant' => $old, 'apres' => $product->fresh()->only(['nom', 'prix_vente_ht', 'prix_achat_ht', 'seuil_alerte', 'quantite'])]
        );

        return response()->json(new ProductResource($product->fresh(['category', 'productType'])));
    }

    public function destroy(int $id): JsonResponse
    {
        if ($this->isRestrictedOperateur()) {
            return response()->json(['message' => 'Action non autorisée dans une organisation multi-points de vente.'], 403);
        }

        $product = Product::findOrFail($id);
        $product->update(['actif' => false]);

        ActivityLogService::log('deleted', 'produit', "Produit '{$product->nom}' supprimé");

        return response()->json(['message' => 'Produit désactivé.']);
    }

    /**
     * Next auto-reference for an organisation: max existing numeric ref + 1.
     * Filters in PHP to stay compatible with both SQLite and Oracle.
     */
    private function nextReference(int $organisationId): string
    {
        $max = Product::withoutGlobalScopes()
            ->where('organisation_id', $organisationId)
            ->where('actif', true)
            ->pluck('reference')
            ->filter(fn($r) => is_numeric($r) && (int) $r > 0)
            ->map(fn($r) => (int) $r)
            ->max() ?? 0;

        return (string) ($max + 1);
    }
}
