<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Fournisseur;
use App\Models\Organisation;
use App\Services\PlanLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FournisseurController extends Controller
{
    public function index(): JsonResponse
    {
        $fournisseurs = Fournisseur::orderBy('nom')->get();
        return response()->json($fournisseurs);
    }

    public function store(Request $request): JsonResponse
    {
        if ($this->isRestrictedOperateur()) {
            return response()->json(['message' => 'Action non autorisée dans une organisation multi-points de vente.'], 403);
        }

        $org = Organisation::with('plan')->findOrFail(app('current_organisation_id'));
        if (!PlanLimitService::check('fournisseurs', $org)) {
            return response()->json(PlanLimitService::limitResponse('fournisseurs', $org), 403);
        }

        $data = $request->validate([
            'nom'       => 'required|string|max:200',
            'telephone' => 'nullable|string|max:30',
            'email'     => 'nullable|email|max:200',
            'adresse'   => 'nullable|string|max:500',
            'note'      => 'nullable|string',
        ]);

        $fournisseur = Fournisseur::create($data);
        return response()->json($fournisseur, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if ($this->isRestrictedOperateur()) {
            return response()->json(['message' => 'Action non autorisée dans une organisation multi-points de vente.'], 403);
        }

        $fournisseur = Fournisseur::findOrFail($id);

        $data = $request->validate([
            'nom'       => 'sometimes|required|string|max:200',
            'telephone' => 'nullable|string|max:30',
            'email'     => 'nullable|email|max:200',
            'adresse'   => 'nullable|string|max:500',
            'note'      => 'nullable|string',
            'active'    => 'sometimes|boolean',
        ]);

        $fournisseur->update($data);
        return response()->json($fournisseur->fresh());
    }

    public function destroy(int $id): JsonResponse
    {
        if ($this->isRestrictedOperateur()) {
            return response()->json(['message' => 'Action non autorisée dans une organisation multi-points de vente.'], 403);
        }

        $fournisseur = Fournisseur::findOrFail($id);

        // Soft-deactivate instead of hard delete if commandes exist
        if ($fournisseur->commandes()->exists()) {
            $fournisseur->update(['active' => false]);
            return response()->json(['message' => 'Fournisseur désactivé (des commandes existent).']);
        }

        $fournisseur->delete();
        return response()->json(null, 204);
    }
}
