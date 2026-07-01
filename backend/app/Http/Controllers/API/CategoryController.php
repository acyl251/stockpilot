<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Organisation;
use App\Services\PlanLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Category::where('actif', true)->orderBy('nom')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $org = Organisation::with('plan')->findOrFail(app('current_organisation_id'));
        if (!PlanLimitService::check('categories', $org)) {
            return response()->json(PlanLimitService::limitResponse('categories', $org), 403);
        }

        $data = $request->validate([
            'nom'         => 'required|string|max:150',
            'description' => 'nullable|string|max:500',
            'couleur'     => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        return response()->json(Category::create($data), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $category = Category::findOrFail($id);

        $data = $request->validate([
            'nom'         => 'sometimes|required|string|max:150',
            'description' => 'nullable|string|max:500',
            'couleur'     => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'actif'       => 'nullable|boolean',
        ]);

        $category->update($data);
        return response()->json($category);
    }

    public function destroy(int $id): JsonResponse
    {
        $category = Category::findOrFail($id);
        $category->update(['actif' => false]);
        return response()->json(['message' => 'Catégorie désactivée.']);
    }
}
