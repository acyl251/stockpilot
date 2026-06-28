<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Fournisseur;
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
