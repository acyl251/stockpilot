<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\StockMovementResource;
use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StockMovementController extends Controller
{
    public function __construct(private StockService $stockService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = StockMovement::with(['product', 'user'])
            ->when($request->product_id, fn($q, $id) => $q->where('product_id', $id))
            ->when($request->type_mouvement, fn($q, $t) => $q->where('type_mouvement', $t))
            ->when($request->date_from, fn($q, $d) => $q->whereDate('date_mouvement', '>=', $d))
            ->when($request->date_to, fn($q, $d) => $q->whereDate('date_mouvement', '<=', $d))
            ->latest('date_mouvement');

        return StockMovementResource::collection($query->paginate($request->per_page ?? 25));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id'     => 'required|integer',
            'type_mouvement' => 'required|in:entree,sortie,ajustement',
            'quantite'       => 'required|numeric|min:0.001',
            'note'           => 'nullable|string|max:500',
            'date_mouvement' => 'nullable|date',
        ]);

        $movement = $this->stockService->createMovement(
            productId:      $validated['product_id'],
            userId:         app('current_user')->id,
            type:           $validated['type_mouvement'],
            quantite:       $validated['quantite'],
            note:           $validated['note'] ?? null,
            dateMouvement:  $validated['date_mouvement'] ?? null,
        );

        return response()->json(new StockMovementResource($movement->load(['product', 'user'])), 201);
    }

    public function show(int $id): JsonResponse
    {
        $movement = StockMovement::with(['product', 'user'])->findOrFail($id);
        return response()->json(new StockMovementResource($movement));
    }
}
