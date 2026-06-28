<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Organisation;
use App\Services\ActivityLogService;
use App\Services\RestaurantCategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganisationController extends Controller
{
    public function __construct(
        private RestaurantCategoryService $restaurantCategories,
    ) {}

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
            'secteur'          => 'nullable|in:commerce,restauration',
        ]);

        $org = Organisation::findOrFail(app('current_organisation_id'));

        // Auto-(re)generate slug when nom changes or when slug is missing
        $newNom = $data['nom'] ?? $org->nom;
        if (! $org->slug || (isset($data['nom']) && $data['nom'] !== $org->nom)) {
            $data['slug'] = Organisation::uniqueSlug($newNom, $org->id);
        }

        $org->update($data);

        $user = app('current_user');
        ActivityLogService::log('updated', 'configuration',
            "Configuration modifiée par {$user->prenom} {$user->nom}"
        );

        // Seed base restauration categories when switching to restauration secteur (idempotent)
        if ($org->fresh()->isRestauration()) {
            $this->restaurantCategories->seedForOrganisation($org->id);
        }

        return response()->json($org->fresh());
    }
}
