<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PointDeVente;
use App\Models\StockParPoint;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PointDeVenteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $pdvs = PointDeVente::withCount('users')
            ->when($request->type, fn($q, $t) => $q->where('type', $t))
            ->orderBy('nom')
            ->get();

        return response()->json($pdvs);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nom'  => 'required|string|max:150',
            'type' => 'required|in:entrepot,point_vente',
        ]);

        $pdv = PointDeVente::create($data);

        return response()->json($pdv, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $pdv = PointDeVente::findOrFail($id);

        $data = $request->validate([
            'nom'   => 'sometimes|required|string|max:150',
            'type'  => 'sometimes|required|in:entrepot,point_vente',
            'actif' => 'sometimes|boolean',
        ]);

        $pdv->update($data);

        return response()->json($pdv);
    }

    public function destroy(int $id): JsonResponse
    {
        $pdv = PointDeVente::findOrFail($id);

        // Désassigner les utilisateurs rattachés
        User::withoutGlobalScopes()
            ->where('organisation_id', app('current_organisation_id'))
            ->where('point_de_vente_id', $id)
            ->update(['point_de_vente_id' => null]);

        $pdv->delete();

        return response()->json(['message' => 'Point de vente supprimé.']);
    }

    public function stock(int $id): JsonResponse
    {
        $pdv = PointDeVente::findOrFail($id);

        $stock = StockParPoint::where('point_de_vente_id', $id)
            ->with('product:id,nom,reference,unite_mesure,seuil_alerte')
            ->get()
            ->map(fn($s) => [
                'product_id'   => $s->product_id,
                'nom'          => $s->product->nom,
                'reference'    => $s->product->reference,
                'unite_mesure' => $s->product->unite_mesure,
                'quantite'     => $s->quantite,
                'en_alerte'    => $s->quantite <= $s->product->seuil_alerte,
            ]);

        return response()->json([
            'point_de_vente' => $pdv,
            'stock'          => $stock,
        ]);
    }

    public function transfer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id'           => 'required|integer|exists:products,id',
            'from_point_de_vente_id' => 'required|integer|exists:points_de_vente,id',
            'to_point_de_vente_id'   => 'required|integer|exists:points_de_vente,id|different:from_point_de_vente_id',
            'quantite'             => 'required|numeric|min:0.001',
        ]);

        $orgId = app('current_organisation_id');

        $stockFrom = StockParPoint::where('product_id', $data['product_id'])
            ->where('point_de_vente_id', $data['from_point_de_vente_id'])
            ->first();

        if (! $stockFrom || $stockFrom->quantite < $data['quantite']) {
            return response()->json([
                'message' => 'Stock insuffisant sur le point de vente source.',
            ], 422);
        }

        $stockFrom->decrement('quantite', $data['quantite']);

        StockParPoint::firstOrCreate(
            ['product_id' => $data['product_id'], 'point_de_vente_id' => $data['to_point_de_vente_id']],
            ['organisation_id' => $orgId, 'quantite' => 0],
        )->increment('quantite', $data['quantite']);

        return response()->json(['message' => 'Transfert effectué.']);
    }
}
