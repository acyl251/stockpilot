<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\AIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function __construct(private AIService $aiService) {}

    public function stockAlerts(): JsonResponse
    {
        $ruptures = Product::where('quantite', '<=', 0)->where('actif', true)
            ->with('category')
            ->get(['id', 'nom', 'reference', 'quantite', 'seuil_alerte', 'unite_mesure', 'category_id']);

        $alertes = Product::whereRaw('quantite > 0 AND quantite <= seuil_alerte')
            ->where('actif', true)
            ->with('category')
            ->get(['id', 'nom', 'reference', 'quantite', 'seuil_alerte', 'unite_mesure', 'category_id']);

        return response()->json([
            'ruptures' => $ruptures,
            'alertes'  => $alertes,
            'total'    => $ruptures->count() + $alertes->count(),
        ]);
    }

    public function aiSuggestions(): JsonResponse
    {
        $org = app('current_user')->organisation;

        if (! $org->hasAIEnabled()) {
            return $this->errorResponse("Les fonctionnalités d'IA ne sont pas incluses dans votre plan actuel.", 403);
        }

        $products = Product::where('actif', true)
            ->select(['id', 'nom', 'reference', 'quantite', 'seuil_alerte', 'unite_mesure'])
            ->get();

        $suggestions = $this->aiService->suggestReorder($products->toArray());

        return response()->json(['suggestions' => $suggestions]);
    }

    public function anomalies(Request $request): JsonResponse
    {
        $org = app('current_user')->organisation;

        if (! $org->hasAIEnabled()) {
            return $this->errorResponse("Les fonctionnalités d'IA ne sont pas incluses dans votre plan actuel.", 403);
        }

        $productId = $request->product_id;

        $movements = \App\Models\StockMovement::when($productId, fn($q) => $q->where('product_id', $productId))
            ->latest('date_mouvement')
            ->limit(200)
            ->get(['product_id', 'type_mouvement', 'quantite', 'date_mouvement'])
            ->toArray();

        $anomalies = $this->aiService->detectAnomaly($movements);

        return response()->json(['anomalies' => $anomalies]);
    }
}
