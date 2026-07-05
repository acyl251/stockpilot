<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\StockMovementResource;
use App\Models\StockMovement;
use App\Services\ActivityLogService;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StockMovementController extends Controller
{
    public function __construct(private StockService $stockService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = app('current_user');

        $query = StockMovement::with(['product', 'user'])
            ->when($request->product_id, fn($q, $id) => $q->where('stock_movements.product_id', $id))
            ->when($request->type_mouvement, fn($q, $t) => $q->where('stock_movements.type_mouvement', $t))
            ->when($request->date_from, fn($q, $d) => $q->whereDate('stock_movements.date_mouvement', '>=', $d))
            ->when($request->date_to, fn($q, $d) => $q->whereDate('stock_movements.date_mouvement', '<=', $d))
            ->when(
                // Opérateur : filtre auto sur son PDV ; admin avec filtre explicite
                $user->role !== 'admin' && $user->point_de_vente_id,
                fn($q) => $q->where('stock_movements.point_de_vente_id', $user->point_de_vente_id)
            )
            ->when(
                $user->role === 'admin' && $request->point_de_vente_id,
                fn($q) => $q->where('stock_movements.point_de_vente_id', $request->point_de_vente_id)
            )
            ->latest('date_mouvement');

        return StockMovementResource::collection($query->paginate($request->per_page ?? 25));
    }

    public function store(Request $request): JsonResponse
    {
        $user = app('current_user');

        // Bloquer l'opérateur dans une organisation multi-PDV
        if ($this->isRestrictedOperateur()) {
            return response()->json([
                'message' => 'Dans une chaîne, seul l\'administrateur peut modifier le stock manuellement. Le stock de votre point de vente est alimenté par les transferts.',
            ], 403);
        }

        // Bloquer l'opérateur sans PDV assigné
        if ($user->role === 'operateur' && ! $user->point_de_vente_id) {
            return response()->json([
                'message' => 'Votre compte n\'est rattaché à aucun point de vente. Contactez votre administrateur.',
            ], 422);
        }

        $validated = $request->validate([
            'product_id'     => 'required|integer',
            'type_mouvement' => 'required|in:entree,sortie,ajustement',
            'quantite'       => 'required|numeric|min:0.001',
            'note'           => 'nullable|string|max:500',
            'date_mouvement' => 'nullable|date',
        ]);

        $pointDeVenteId = $user->point_de_vente_id;

        $movement = $this->stockService->createMovement(
            productId:      $validated['product_id'],
            userId:         $user->id,
            type:           $validated['type_mouvement'],
            quantite:       $validated['quantite'],
            note:           $validated['note'] ?? null,
            dateMouvement:  $validated['date_mouvement'] ?? null,
            pointDeVenteId: $pointDeVenteId,
        );

        $movement->load(['product', 'user']);
        $produit = $movement->product->nom ?? '?';
        $unite   = $movement->product->unite_mesure ?? '';
        $signe   = $validated['type_mouvement'] === 'entree' ? '+' : '-';
        $verb    = $validated['type_mouvement'] === 'entree' ? 'Entrée stock' : 'Sortie stock';

        ActivityLogService::log('created', 'stock',
            "{$verb} : {$signe}{$validated['quantite']} {$unite} de '{$produit}'"
        );

        return response()->json(new StockMovementResource($movement), 201);
    }

    public function show(int $id): JsonResponse
    {
        $movement = StockMovement::with(['product', 'user'])->findOrFail($id);
        return response()->json(new StockMovementResource($movement));
    }
}
