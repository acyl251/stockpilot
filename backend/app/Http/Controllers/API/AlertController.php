<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CommandeFournisseurItem;
use App\Models\Product;
use App\Models\StockParPoint;
use App\Services\AIService;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AlertController extends Controller
{
    public function __construct(private AIService $aiService) {}

    /** Envoie un récapitulatif des produits à réapprovisionner par WhatsApp. */
    public function notifyStock(Request $request, WhatsAppService $wa): JsonResponse
    {
        $user  = app('current_user');
        $org   = $user->organisation;
        $phone = $request->input('telephone') ?: $org->telephone;

        if (! $phone) {
            return $this->errorResponse(
                'Aucun numéro de notification. Renseignez le téléphone dans Configuration.', 422
            );
        }

        $pdvId = ($user->role === 'operateur' && $user->point_de_vente_id)
            ? (int) $user->point_de_vente_id
            : null;

        $produits = $pdvId !== null
            ? $this->getAlertProductsForPdv($pdvId)
            : Product::whereRaw('quantite <= seuil_alerte')
                ->where('actif', true)
                ->where('type', '!=', 'compose')
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
        $user  = app('current_user');
        $pdvId = ($user->role === 'operateur' && $user->point_de_vente_id)
            ? (int) $user->point_de_vente_id
            : null;

        if ($pdvId !== null) {
            $ruptures = $this->getAlertProductsForPdv($pdvId, 'rupture');
            $alertes  = $this->getAlertProductsForPdv($pdvId, 'alerte');
        } else {
            $ruptures = Product::where('quantite', '<=', 0)->where('actif', true)
                ->where('type', '!=', 'compose')
                ->with('category')
                ->get(['id', 'nom', 'reference', 'quantite', 'seuil_alerte', 'unite_mesure', 'category_id']);

            $alertes = Product::whereRaw('quantite > 0 AND quantite <= seuil_alerte')
                ->where('actif', true)
                ->where('type', '!=', 'compose')
                ->with('category')
                ->get(['id', 'nom', 'reference', 'quantite', 'seuil_alerte', 'unite_mesure', 'category_id']);
        }

        return response()->json([
            'ruptures' => $ruptures,
            'alertes'  => $alertes,
            'total'    => $ruptures->count() + $alertes->count(),
        ]);
    }

    /**
     * Fetch products for a specific PDV with their per-PDV stock overriding the global quantite.
     * $mode: 'alerte' | 'rupture' | null (both combined, for WhatsApp notify)
     */
    private function getAlertProductsForPdv(int $pdvId, ?string $mode = null)
    {
        $query = Product::query()
            ->where('actif', true)
            ->where('type', '!=', 'compose')
            ->with('category');

        if ($mode === 'alerte') {
            $query->whereExists(fn($sub) => $sub
                ->from('stock_par_point')
                ->whereColumn('stock_par_point.product_id', 'products.id')
                ->where('stock_par_point.point_de_vente_id', $pdvId)
                ->whereRaw('stock_par_point.quantite > 0')
                ->whereRaw('stock_par_point.quantite <= products.seuil_alerte')
            );
        } elseif ($mode === 'rupture') {
            $query->where(fn($w) => $w
                ->whereNotExists(fn($sub) => $sub
                    ->from('stock_par_point')
                    ->whereColumn('stock_par_point.product_id', 'products.id')
                    ->where('stock_par_point.point_de_vente_id', $pdvId)
                    ->where('stock_par_point.quantite', '>', 0)
                )
            );
        } else {
            // Both alerte + rupture (for WhatsApp notify)
            $query->where(fn($w) => $w
                ->whereExists(fn($sub) => $sub
                    ->from('stock_par_point')
                    ->whereColumn('stock_par_point.product_id', 'products.id')
                    ->where('stock_par_point.point_de_vente_id', $pdvId)
                    ->whereRaw('stock_par_point.quantite <= products.seuil_alerte')
                )
                ->orWhereNotExists(fn($sub) => $sub
                    ->from('stock_par_point')
                    ->whereColumn('stock_par_point.product_id', 'products.id')
                    ->where('stock_par_point.point_de_vente_id', $pdvId)
                    ->where('stock_par_point.quantite', '>', 0)
                )
            );
            $query->limit(30);
        }

        $products = $query->get(['id', 'nom', 'reference', 'quantite', 'seuil_alerte', 'unite_mesure', 'category_id']);

        // Override quantite with per-PDV stock
        $stockMap = StockParPoint::where('point_de_vente_id', $pdvId)
            ->whereIn('product_id', $products->pluck('id'))
            ->pluck('quantite', 'product_id');

        $products->each(function (Product $p) use ($stockMap) {
            $p->quantite = (float) ($stockMap[$p->id] ?? 0.0);
        });

        return $products;
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

        // Appel GPT désactivé — retourne tableau vide
        // $suggestions = $this->aiService->suggestReorder($products->toArray());
        $suggestions = [];

        return response()->json(['suggestions' => $suggestions]);
    }

    /**
     * Products below alert threshold with their last-used supplier.
     */
    public function commandesSuggerees(): JsonResponse
    {
        $orgId = app('current_organisation_id');

        // Products below threshold
        $produits = Product::whereRaw('quantite <= seuil_alerte')
            ->where('actif', true)
            ->where('type', '!=', 'compose')
            ->get(['id', 'nom', 'quantite', 'seuil_alerte', 'unite_mesure']);

        if ($produits->isEmpty()) {
            return response()->json([]);
        }

        // Last supplier per product (most recent commande_fournisseur with that product)
        $productIds = $produits->pluck('id');

        $lastSuppliers = CommandeFournisseurItem::join('commandes_fournisseur', 'commandes_fournisseur_items.commande_id', '=', 'commandes_fournisseur.id')
            ->join('fournisseurs', 'commandes_fournisseur.fournisseur_id', '=', 'fournisseurs.id')
            ->where('commandes_fournisseur.organisation_id', $orgId)
            ->whereIn('commandes_fournisseur_items.product_id', $productIds)
            ->select(
                'commandes_fournisseur_items.product_id',
                'fournisseurs.id as fournisseur_id',
                'fournisseurs.nom as fournisseur_nom',
                DB::raw('MAX(commandes_fournisseur.date_commande) as last_date'),
            )
            ->groupBy('commandes_fournisseur_items.product_id', 'fournisseurs.id', 'fournisseurs.nom')
            ->orderByDesc('last_date')
            ->get()
            ->keyBy('product_id');

        $result = $produits->map(function (Product $p) use ($lastSuppliers) {
            $supplier = $lastSuppliers->get($p->id);
            return [
                'product_id'   => $p->id,
                'nom'          => $p->nom,
                'quantite'     => (float) $p->quantite,
                'seuil_alerte' => (float) $p->seuil_alerte,
                'unite'        => $p->unite_mesure,
                'fournisseur'  => $supplier
                    ? ['id' => $supplier->fournisseur_id, 'nom' => $supplier->fournisseur_nom]
                    : null,
            ];
        });

        return response()->json($result->values());
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

        // Appel GPT désactivé — retourne tableau vide
        // $anomalies = $this->aiService->detectAnomaly($movements);
        $anomalies = [];

        return response()->json(['anomalies' => $anomalies]);
    }
}
