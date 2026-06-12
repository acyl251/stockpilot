<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::withoutGlobalScopes()
            ->where('organisation_id', app('current_organisation_id'))
            ->orderBy('nom')
            ->get(['id', 'nom', 'prenom', 'email', 'role', 'actif', 'created_at']);

        return response()->json($users);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nom'     => 'required|string|max:100',
            'prenom'  => 'required|string|max:100',
            'email'   => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role'    => 'required|in:admin,gestionnaire,operateur',
        ]);

        $data['password']        = Hash::make($data['password']);
        $data['organisation_id'] = app('current_organisation_id');

        $user = User::create($data);

        return response()->json($user->only(['id', 'nom', 'prenom', 'email', 'role', 'actif']), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::withoutGlobalScopes()
            ->where('organisation_id', app('current_organisation_id'))
            ->findOrFail($id);

        $data = $request->validate([
            'nom'     => 'sometimes|required|string|max:100',
            'prenom'  => 'sometimes|required|string|max:100',
            'role'    => 'sometimes|required|in:admin,gestionnaire,operateur',
            'actif'   => 'nullable|boolean',
            'password' => 'nullable|string|min:8',
        ]);

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);
        return response()->json($user->only(['id', 'nom', 'prenom', 'email', 'role', 'actif']));
    }

    public function destroy(int $id): JsonResponse
    {
        $currentUser = app('current_user');

        if ($currentUser->id === $id) {
            return $this->errorResponse('Vous ne pouvez pas désactiver votre propre compte.', 422);
        }

        $user = User::withoutGlobalScopes()->findOrFail($id);
        $user->update(['actif' => !$user->actif]);

        return response()->json(['actif' => $user->actif]);
    }
}
