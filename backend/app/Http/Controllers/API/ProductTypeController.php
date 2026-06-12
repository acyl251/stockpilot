<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ProductType;
use App\Models\TypeAttribute;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductTypeController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(ProductType::with('attributes')->orderBy('nom')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nom'         => 'required|string|max:150',
            'icone'       => 'nullable|string|max:50',
            'description' => 'nullable|string|max:500',
            'attributs'   => 'nullable|array',
            'attributs.*.nom'          => 'required|string|max:100',
            'attributs.*.label'        => 'required|string|max:150',
            'attributs.*.type_donnee'  => 'required|in:text,number,date,boolean,select',
            'attributs.*.obligatoire'  => 'nullable|boolean',
            'attributs.*.valeur_defaut' => 'nullable|string',
            'attributs.*.options_select' => 'nullable|array',
            'attributs.*.ordre'        => 'nullable|integer',
        ]);

        return DB::transaction(function () use ($data) {
            $type = ProductType::create([
                'nom'         => $data['nom'],
                'icone'       => $data['icone'] ?? null,
                'description' => $data['description'] ?? null,
            ]);

            foreach ($data['attributs'] ?? [] as $i => $attr) {
                TypeAttribute::create(array_merge($attr, [
                    'product_type_id' => $type->id,
                    'ordre'           => $attr['ordre'] ?? $i,
                    'options_select'  => isset($attr['options_select'])
                        ? json_encode($attr['options_select'])
                        : null,
                ]));
            }

            return response()->json($type->load('attributes'), 201);
        });
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(ProductType::with('attributes')->findOrFail($id));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $type = ProductType::findOrFail($id);

        $data = $request->validate([
            'nom'         => 'sometimes|required|string|max:150',
            'icone'       => 'nullable|string|max:50',
            'description' => 'nullable|string|max:500',
            'actif'       => 'nullable|boolean',
        ]);

        $type->update($data);
        return response()->json($type->load('attributes'));
    }

    public function destroy(int $id): JsonResponse
    {
        $type = ProductType::findOrFail($id);
        $type->update(['actif' => false]);
        return response()->json(['message' => 'Type de produit désactivé.']);
    }
}
