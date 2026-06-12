<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Product::with(['category', 'productType'])
            ->when($request->search, fn($q, $s) => $q->where('nom', 'LIKE', "%$s%")
                ->orWhere('reference', 'LIKE', "%$s%"))
            ->when($request->category_id, fn($q, $id) => $q->where('category_id', $id))
            ->when($request->product_type_id, fn($q, $id) => $q->where('product_type_id', $id))
            ->when($request->statut === 'alerte', fn($q) => $q->whereRaw(
                'quantite > 0 AND quantite <= seuil_alerte'
            ))
            ->when($request->statut === 'rupture', fn($q) => $q->where('quantite', '<=', 0))
            // Active products only by default; pass ?actif=0 to include deactivated ones.
            ->when(
                $request->has('actif'),
                fn($q) => $q->where('actif', $request->boolean('actif')),
                fn($q) => $q->where('actif', true)
            )
            ->orderBy('nom');

        return ProductResource::collection($query->paginate($request->per_page ?? 20));
    }

    /** Check whether a product reference is still available within the tenant. */
    public function checkReference(Request $request): JsonResponse
    {
        $request->validate([
            'reference'  => 'required|string|max:100',
            'exclude_id' => 'nullable|integer',
        ]);

        $exists = Product::where('reference', $request->reference)
            ->when($request->exclude_id, fn($q, $id) => $q->where('id', '!=', $id))
            ->exists();

        return response()->json(['available' => ! $exists]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id'     => 'nullable|integer',
            'product_type_id' => 'nullable|integer',
            'nom'             => 'required|string|max:255',
            'reference'       => 'nullable|string|max:100',
            'description'     => 'nullable|string|max:1000',
            'seuil_alerte'    => 'required|numeric|min:0',
            'unite_mesure'    => 'nullable|string|max:30',
            'prix_achat_ht'   => 'required|numeric|min:0',
            'taux_tva'        => 'nullable|numeric|min:0|max:100',
            'prix_vente_ht'   => 'required|numeric|min:0',
            'attributs'       => 'nullable|array',
        ]);

        $product = Product::create($validated);

        return response()->json(new ProductResource($product->fresh(['category', 'productType'])), 201);
    }

    public function show(int $id): JsonResponse
    {
        $product = Product::with(['category', 'productType', 'stockMovements' => fn($q) => $q->latest()->limit(10)])
            ->findOrFail($id);

        return response()->json(new ProductResource($product));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'category_id'     => 'nullable|integer',
            'product_type_id' => 'nullable|integer',
            'nom'             => 'sometimes|required|string|max:255',
            'reference'       => 'nullable|string|max:100',
            'description'     => 'nullable|string|max:1000',
            'seuil_alerte'    => 'sometimes|required|numeric|min:0',
            'unite_mesure'    => 'nullable|string|max:30',
            'prix_achat_ht'   => 'sometimes|required|numeric|min:0',
            'taux_tva'        => 'nullable|numeric|min:0|max:100',
            'prix_vente_ht'   => 'sometimes|required|numeric|min:0',
            'attributs'       => 'nullable|array',
            'actif'           => 'nullable|boolean',
        ]);

        $product->update($validated);

        return response()->json(new ProductResource($product->fresh(['category', 'productType'])));
    }

    public function destroy(int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $product->update(['actif' => false]);

        return response()->json(['message' => 'Produit désactivé.']);
    }
}
