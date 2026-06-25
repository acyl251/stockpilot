<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Organisation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganisationController extends Controller
{
    /** Infos de l'organisation courante (pour l'écran Facturation). */
    public function show(): JsonResponse
    {
        $org = Organisation::with('plan:id,nom')->findOrFail(app('current_organisation_id'));

        return response()->json($org);
    }

    /** Mise à jour des informations légales / de facturation (admin uniquement). */
    public function update(Request $request): JsonResponse
    {
        if (! app('current_user')->isAdmin()) {
            return $this->errorResponse('Seul un administrateur peut modifier ces informations.', 403);
        }

        $data = $request->validate([
            'nom'              => 'sometimes|required|string|max:200',
            'telephone'        => 'nullable|string|max:20',
            'adresse'          => 'nullable|string|max:500',
            'matricule_fiscal' => 'nullable|string|max:20',
        ]);

        $org = Organisation::findOrFail(app('current_organisation_id'));
        $org->update($data);

        return response()->json($org->fresh());
    }
}
