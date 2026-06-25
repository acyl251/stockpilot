<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\AIService;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function __construct(private AIService $aiService) {}

    /** Envoie un récapitulatif des produits à réapprovisionner par WhatsApp. */
    public function notifyStock(Request $request, WhatsAppService $wa): JsonResponse
    {
        $org   = app('current_user')->organisation;
        $phone = $request->input('telephone') ?: $org->telephone;

        if (! $phone) {
            return $this->errorResponse(
                'Aucun numéro de notification. Renseignez le téléphone dans Configuration.', 422
            );
        }

        $produits = Product::whereRaw('quantite <= seuil_alerte')
            ->where('actif', true)
            ->orderBy('quantite')
            ->limit(30)
            ->get(['nom', 'quantite', 'unite_mesure']);

        if ($produits->isEmpty()) {
            return $this->errorResponse('Aucune alerte de stock à envoyer.', 422);
        }

        $liste = $produits
            ->map(fn($p) => "• {$p->nom} : {$p->quantite} {$p->unite_mesure}")
            ->implode("\n");

        $message = str_replace(
            [':org', ':liste'],
            [$org->nom, $liste],
            config('whatsapp.templates.stock'),
        );

        $result = $wa->send($phone, $message);

        return response()->json($result + ['message_text' => $message, 'nb_produits' => $produits->count()]);
    }

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
