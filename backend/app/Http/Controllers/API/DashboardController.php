<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\AIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct(private AIService $aiService) {}

    public function index(): JsonResponse
    {
        $orgId = app('current_organisation_id');
        $org   = app('current_user')->organisation;

        // Real-time KPIs
        $totalProduits   = Product::where('actif', true)->count();
        $totalRuptures   = Product::where('quantite', '<=', 0)->where('actif', true)->count();
        $totalAlertes    = Product::whereRaw('quantite > 0 AND quantite <= seuil_alerte')
            ->where('actif', true)->count();

        $valeurStock = Product::where('actif', true)
            ->selectRaw('SUM(quantite * prix_achat_ht) as total')
            ->value('total') ?? 0;

        $mouvementsAujourdhui = StockMovement::whereDate('date_mouvement', today())->count();

        // DB-agnostic date truncation
        $dateTrunc = DB::connection()->getDriverName() === 'oracle'
            ? 'TRUNC(date_mouvement)'
            : "DATE(date_mouvement)";

        // Chiffre d'affaires = sorties × prix_vente_ht du produit
        $caMois = StockMovement::where('stock_movements.type_mouvement', 'sortie')
            ->join('products', 'stock_movements.product_id', '=', 'products.id')
            ->whereMonth('stock_movements.date_mouvement', now()->month)
            ->whereYear('stock_movements.date_mouvement', now()->year)
            ->selectRaw('SUM(stock_movements.quantite * products.prix_vente_ht) as total')
            ->value('total') ?? 0;

        $ca7j = StockMovement::where('stock_movements.type_mouvement', 'sortie')
            ->join('products', 'stock_movements.product_id', '=', 'products.id')
            ->where('stock_movements.date_mouvement', '>=', now()->subDays(7))
            ->selectRaw('SUM(stock_movements.quantite * products.prix_vente_ht) as total')
            ->value('total') ?? 0;

        $ca7jDetail = StockMovement::where('stock_movements.type_mouvement', 'sortie')
            ->join('products', 'stock_movements.product_id', '=', 'products.id')
            ->where('stock_movements.date_mouvement', '>=', now()->subDays(7))
            ->select(
                DB::raw("$dateTrunc as date"),
                DB::raw('SUM(stock_movements.quantite * products.prix_vente_ht) as ca')
            )
            ->groupBy(DB::raw($dateTrunc))
            ->orderBy(DB::raw($dateTrunc))
            ->get();

        $mouvements7j = StockMovement::select(
                DB::raw("$dateTrunc as date"),
                DB::raw("COUNT(*) as total"),
                DB::raw("SUM(CASE WHEN type_mouvement='entree' THEN 1 ELSE 0 END) as entrees"),
                DB::raw("SUM(CASE WHEN type_mouvement='sortie' THEN 1 ELSE 0 END) as sorties")
            )
            ->where('date_mouvement', '>=', now()->subDays(7))
            ->groupBy(DB::raw($dateTrunc))
            ->orderBy(DB::raw($dateTrunc))
            ->get();

        $response = [
            'kpis' => [
                'total_produits'        => $totalProduits,
                'total_ruptures'        => $totalRuptures,
                'total_alertes'         => $totalAlertes,
                'valeur_stock'          => round($valeurStock, 3),
                'mouvements_aujourdhui' => $mouvementsAujourdhui,
                'ca_mois'               => round($caMois, 3),
                'ca_7j'                 => round($ca7j, 3),
            ],
            'mouvements_7j' => $mouvements7j,
            'ca_7j_detail'  => $ca7jDetail,
        ];

        // Welcome banner — catalog was pre-filled by AI at org creation
        if (($org->ia_catalog_seeded_count ?? 0) > 0) {
            $response['bienvenue_ia'] = [
                'secteur'     => $org->secteur,
                'nb_produits' => (int) $org->ia_catalog_seeded_count,
                'date'        => optional($org->ia_catalog_seeded_at)->toIso8601String(),
            ];
        }

        // AI predictive KPIs if plan allows
        if ($org->hasAIEnabled()) {
            $topProducts = Product::where('actif', true)
                ->orderBy('quantite')
                ->limit(10)
                ->get(['id', 'nom', 'quantite', 'seuil_alerte'])
                ->toArray();

            $response['kpis_ia'] = $this->aiService->predictiveKpis($topProducts);
        }

        return response()->json($response);
    }

    public function forecast(int $productId): JsonResponse
    {
        $org = app('current_user')->organisation;

        if (! $org->hasAIEnabled()) {
            return $this->errorResponse("Les fonctionnalités d'IA ne sont pas incluses dans votre plan actuel.", 403);
        }

        $product = Product::findOrFail($productId);

        $history = StockMovement::where('product_id', $productId)
            ->where('type_mouvement', 'sortie')
            ->latest('date_mouvement')
            ->limit(90)
            ->get(['quantite', 'date_mouvement'])
            ->toArray();

        $forecast = $this->aiService->forecastDemand($product->toArray(), $history);

        return response()->json([
            'product'  => $product->only(['id', 'nom', 'reference', 'quantite', 'unite_mesure']),
            'forecast' => $forecast,
        ]);
    }
}
