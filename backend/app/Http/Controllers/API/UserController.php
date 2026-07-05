<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Organisation;
use App\Models\User;
use App\Services\PlanLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::withoutGlobalScopes()
            ->where('organisation_id', app('current_organisation_id'))
            ->with('pointDeVente:id,nom')
            ->orderBy('nom')
            ->get(['id', 'nom', 'prenom', 'email', 'role', 'actif', 'point_de_vente_id', 'created_at']);

        return response()->json($users);
    }

    public function store(Request $request): JsonResponse
    {
        $org = Organisation::with('plan')->findOrFail(app('current_organisation_id'));
        if (!PlanLimitService::check('utilisateurs', $org)) {
            return response()->json(PlanLimitService::limitResponse('utilisateurs', $org), 403);
        }

        $data = $request->validate([
            'nom'              => 'required|string|max:100',
            'prenom'           => 'required|string|max:100',
            'email'            => 'required|email|unique:users,email',
            'password'         => 'required|string|min:8',
            'role'             => 'required|in:admin,gestionnaire,operateur',
            'point_de_vente_id' => 'nullable|integer|exists:points_de_vente,id',
        ]);

        $data['password']        = Hash::make($data['password']);
        $data['organisation_id'] = app('current_organisation_id');

        $user = User::create($data);

        return response()->json($user->only(['id', 'nom', 'prenom', 'email', 'role', 'actif', 'point_de_vente_id']), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::withoutGlobalScopes()
            ->where('organisation_id', app('current_organisation_id'))
            ->findOrFail($id);

        $data = $request->validate([
            'nom'              => 'sometimes|required|string|max:100',
            'prenom'           => 'sometimes|required|string|max:100',
            'role'             => 'sometimes|required|in:admin,gestionnaire,operateur',
            'actif'            => 'nullable|boolean',
            'password'         => 'nullable|string|min:8',
            'point_de_vente_id' => 'nullable|integer|exists:points_de_vente,id',
        ]);

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        try {
            $user->update($data);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
            ], 500);
        }

        $user->load('pointDeVente:id,nom');
        return response()->json($user->only(['id', 'nom', 'prenom', 'email', 'role', 'actif', 'point_de_vente_id']) + ['point_de_vente' => $user->pointDeVente]);
    }

    public function destroy(int $id): JsonResponse
    {
        $currentUser = app('current_user');
        $orgId       = app('current_organisation_id');

        if (!in_array($currentUser->role, ['admin', 'super_admin'])) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        if ((int) $currentUser->id === $id) {
            return response()->json(['message' => 'Vous ne pouvez pas supprimer votre propre compte.'], 422);
        }

        $user = User::withoutGlobalScopes()
            ->where('organisation_id', $orgId)
            ->whereNull('deleted_at')
            ->findOrFail($id);

        if ($user->role === 'super_admin') {
            return response()->json(['message' => 'Impossible de supprimer un super administrateur.'], 403);
        }

        // Bloquer si dernier admin de l'org
        if ($user->role === 'admin') {
            $adminCount = User::withoutGlobalScopes()
                ->where('organisation_id', $orgId)
                ->where('role', 'admin')
                ->whereNull('deleted_at')
                ->count();
            if ($adminCount <= 1) {
                return response()->json(['message' => "Impossible : cet utilisateur est le seul admin de l'organisation."], 422);
            }
        }

        // Soft delete si l'utilisateur a des données liées, suppression physique sinon
        $hasData = DB::table('sales')->where('user_id', $id)->exists()
            || DB::table('stock_movements')->where('user_id', $id)->exists()
            || DB::table('client_payments')->where('user_id', $id)->exists();

        if ($hasData) {
            $user->actif = false;
            $user->save();
            $user->delete();
        } else {
            $user->forceDelete();
        }

        return response()->json(['message' => 'Utilisateur supprimé.']);
    }
}
