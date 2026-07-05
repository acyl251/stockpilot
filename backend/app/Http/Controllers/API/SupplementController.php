<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Organisation;
use App\Models\Product;
use App\Models\Supplement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplementController extends Controller
{
    private function requireRestauration(): ?JsonResponse
    {
        $org = Organisation::findOrFail(app('current_organisation_id'));
        if (! $org->isRestauration()) {
            return response()->json(['message' => 'Les suppléments ne sont disponibles que pour le secteur restauration.'], 403);
        }
        return null;
    }

    public function index(): JsonResponse
    {
        if ($error = $this->requireRestauration()) return $error;

        $supplements = Supplement::with('ingredient:id,nom,unite_mesure,prix_achat_ht')
            ->orderBy('nom')
            ->get()
            ->map(fn($s) => $this->format($s));

        return response()->json($supplements);
    }

    public function store(Request $request): JsonResponse
    {
        if ($this->isRestrictedOperateur()) {
            return response()->json(['message' => 'Action non autorisée dans une organisation multi-points de vente.'], 403);
        }
        if ($error = $this->requireRestauration()) return $error;

        $validated = $request->validate([
            'nom'           => 'required|string|max:200',
            'prix_vente'    => 'required|numeric|min:0',
            'ingredient_id' => 'required|integer|exists:products,id,actif,1',
            'quantite'      => 'required|numeric|min:0.001',
            'unite'         => 'nullable|string|max:30',
            'active'        => 'nullable|boolean',
        ]);

        // Ensure ingredient belongs to the same tenant and is a simple product
        $ingredient = Product::findOrFail($validated['ingredient_id']);
        if ($ingredient->type !== Product::TYPE_SIMPLE) {
            return response()->json(['message' => 'L\'ingrédient doit être un produit de type simple.'], 422);
        }

        $supplement = Supplement::create($validated);

        return response()->json($this->format($supplement->load('ingredient:id,nom,unite_mesure,prix_achat_ht')), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if ($this->isRestrictedOperateur()) {
            return response()->json(['message' => 'Action non autorisée dans une organisation multi-points de vente.'], 403);
        }
        if ($error = $this->requireRestauration()) return $error;

        $supplement = Supplement::findOrFail($id);

        $validated = $request->validate([
            'nom'           => 'sometimes|required|string|max:200',
            'prix_vente'    => 'sometimes|required|numeric|min:0',
            'ingredient_id' => 'sometimes|required|integer|exists:products,id,actif,1',
            'quantite'      => 'sometimes|required|numeric|min:0.001',
            'unite'         => 'nullable|string|max:30',
            'active'        => 'nullable|boolean',
        ]);

        if (isset($validated['ingredient_id'])) {
            $ingredient = Product::findOrFail($validated['ingredient_id']);
            if ($ingredient->type !== Product::TYPE_SIMPLE) {
                return response()->json(['message' => 'L\'ingrédient doit être un produit de type simple.'], 422);
            }
        }

        $supplement->update($validated);

        return response()->json($this->format($supplement->load('ingredient:id,nom,unite_mesure,prix_achat_ht')));
    }

    public function destroy(int $id): JsonResponse
    {
        if ($this->isRestrictedOperateur()) {
            return response()->json(['message' => 'Action non autorisée dans une organisation multi-points de vente.'], 403);
        }
        if ($error = $this->requireRestauration()) return $error;

        Supplement::findOrFail($id)->delete();

        return response()->json(['message' => 'Supplément supprimé.']);
    }

    private function format(Supplement $s): array
    {
        $ing = $s->ingredient;
        return [
            'id'            => $s->id,
            'nom'           => $s->nom,
            'prix_vente'    => $s->prix_vente,
            'ingredient_id' => $s->ingredient_id,
            'ingredient'    => $ing ? [
                'id'          => $ing->id,
                'nom'         => $ing->nom,
                'unite_mesure'=> $ing->unite_mesure,
                'prix_achat'  => (float) $ing->prix_achat_ht,
            ] : null,
            'quantite'      => $s->quantite,
            'unite'         => $s->unite,
            'active'        => $s->active,
        ];
    }
}
