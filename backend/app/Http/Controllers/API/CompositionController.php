<?php

namespace App\Http\Controllers\API;

use App\Helpers\UnitConversionHelper;
use App\Http\Controllers\Controller;
use App\Models\Composition;
use App\Models\Organisation;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompositionController extends Controller
{
    private function requireRestauration(): ?JsonResponse
    {
        $org = Organisation::findOrFail(app('current_organisation_id'));
        if (! $org->isRestauration()) {
            return response()->json([
                'message' => 'Les recettes/fiches techniques ne sont disponibles que pour le secteur restauration.',
            ], 403);
        }
        return null;
    }

    /** GET /products/{product}/composition */
    public function index(int $productId): JsonResponse
    {
        if ($error = $this->requireRestauration()) return $error;

        $product = Product::findOrFail($productId);

        $lines = Composition::where('produit_compose_id', $productId)
            ->with('composant:id,nom,unite_mesure,quantite,prix_achat_ht')
            ->get()
            ->map(function ($c) {
                // In restauration, prix_achat_ht IS the TTC price entered by the user.
                // Never apply TVA here — taux_tva on old products would corrupt the cost.
                $prixAchat    = (float) $c->composant->prix_achat_ht;
                $uniteStock   = $c->composant->unite_mesure ?? '';
                $uniteRecette = $c->unite ?? $uniteStock;
                $factor       = UnitConversionHelper::getConversionFactor($uniteStock, $uniteRecette);
                $coutLigne    = $factor !== null
                    ? round($prixAchat * (float) $c->quantite * $factor, 3)
                    : null;

                return [
                    'id'                => $c->id,
                    'composant_id'      => $c->composant_id,
                    'composant'         => [
                        'id'           => $c->composant->id,
                        'nom'          => $c->composant->nom,
                        'unite_mesure' => $c->composant->unite_mesure,
                        'quantite'     => (float) $c->composant->quantite,
                        'prix_achat'   => $prixAchat,
                    ],
                    'quantite'          => (float) $c->quantite,
                    'unite'             => $c->unite,
                    'conversion_factor' => $factor,
                    'cout_ligne'        => $coutLigne,
                    'incompatible'      => $factor === null,
                ];
            });

        // ── Food cost calculation (with unit conversion) ───────────────────────
        $coutMatiere      = 0.0;
        $coutComplet      = count($lines) > 0;
        $hasIncompatible  = false;

        foreach ($lines as $line) {
            if ($line['incompatible']) {
                $hasIncompatible = true;
                $coutComplet     = false;
                continue;
            }
            if (($line['composant']['prix_achat'] ?? 0.0) <= 0) {
                $coutComplet = false;
            }
            $coutMatiere += $line['cout_ligne'] ?? 0.0;
        }
        $coutMatiere = round($coutMatiere, 3);

        // prix_vente_ht IS the TTC sale price in restauration (taux_tva = 0)
        $prixVente    = (float) $product->prix_vente_ht;
        $foodCostPct  = $prixVente > 0 ? round($coutMatiere / $prixVente * 100, 1) : null;
        $margeMatiere = round($prixVente - $coutMatiere, 3);

        return response()->json([
            'produit_compose_id' => $product->id,
            'nom'                => $product->nom,
            'lignes'             => $lines,
            'cout_matiere'       => $coutMatiere,
            'food_cost_percent'  => $foodCostPct,
            'marge_matiere'      => $margeMatiere,
            'cout_complet'       => $coutComplet,
            'has_incompatible'   => $hasIncompatible,
        ]);
    }

    /** POST /products/{product}/composition */
    public function store(Request $request, int $productId): JsonResponse
    {
        if ($error = $this->requireRestauration()) return $error;

        $product = Product::findOrFail($productId);

        if (! $product->isCompose()) {
            return response()->json(['message' => 'Ce produit n\'est pas de type composé.'], 422);
        }

        $validated = $request->validate([
            'composant_id' => 'required|integer|exists:products,id,actif,1|different:' . $productId,
            'quantite'     => 'required|numeric|min:0.001',
            'unite'        => 'nullable|string|max:30',
        ]);

        // Ensure composant belongs to same tenant (TenantScope on Product handles this)
        Product::findOrFail($validated['composant_id']);

        $comp = Composition::updateOrCreate(
            ['produit_compose_id' => $productId, 'composant_id' => $validated['composant_id']],
            ['quantite' => $validated['quantite'], 'unite' => $validated['unite'] ?? null],
        );

        return response()->json($comp->load('composant:id,nom,unite_mesure'), 201);
    }

    /** PATCH /products/{product}/composition/{composition} */
    public function update(Request $request, int $productId, int $compositionId): JsonResponse
    {
        if ($error = $this->requireRestauration()) return $error;

        $comp = Composition::where('produit_compose_id', $productId)->findOrFail($compositionId);

        $validated = $request->validate([
            'quantite' => 'sometimes|required|numeric|min:0.001',
            'unite'    => 'nullable|string|max:30',
        ]);

        $comp->update($validated);

        return response()->json($comp->load('composant:id,nom,unite_mesure'));
    }

    /** DELETE /products/{product}/composition/{composition} */
    public function destroy(int $productId, int $compositionId): JsonResponse
    {
        if ($error = $this->requireRestauration()) return $error;

        $comp = Composition::where('produit_compose_id', $productId)->findOrFail($compositionId);
        $comp->delete();

        return response()->json(['message' => 'Ligne supprimée.']);
    }
}
