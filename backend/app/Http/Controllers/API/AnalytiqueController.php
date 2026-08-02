<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Order;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\Sale;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalytiqueController extends Controller
{
    private function authorize(): bool
    {
        return in_array(app('current_user')->role, ['admin', 'gestionnaire']);
    }

    private function denied(): JsonResponse
    {
        return $this->errorResponse('Accès réservé aux administrateurs et gestionnaires.', 403);
    }

    /**
     * Resolve [start, end, prevStart, prevEnd] Carbon boundaries for a period key.
     * All dates use the app timezone (Africa/Tunis).
     */
    private function resolvePeriod(string $periode): array
    {
        $now = now();

        return match ($periode) {
            'today' => [
                'start'     => $now->copy()->startOfDay(),
                'end'       => $now->copy()->endOfDay(),
                'prevStart' => $now->copy()->subDay()->startOfDay(),
                'prevEnd'   => $now->copy()->subDay()->endOfDay(),
            ],
            'month' => [
                'start'     => $now->copy()->startOfMonth(),
                'end'       => $now->copy()->endOfDay(),
                'prevStart' => $now->copy()->subMonthNoOverflow()->startOfMonth(),
                'prevEnd'   => $now->copy()->subMonthNoOverflow()->endOfMonth(),
            ],
            default => [ // 'week'
                'start'     => $now->copy()->startOfWeek(Carbon::MONDAY),
                'end'       => $now->copy()->endOfDay(),
                'prevStart' => $now->copy()->subWeek()->startOfWeek(Carbon::MONDAY),
                'prevEnd'   => $now->copy()->subWeek()->endOfWeek(Carbon::SUNDAY),
            ],
        };
    }

    /** DB-agnostic DATE() truncation expression for the current connection. */
    private function dateExpr(string $column): string
    {
        return DB::connection()->getDriverName() === 'oracle'
            ? "TRUNC({$column})"
            : "DATE({$column})";
    }

    /** DB-agnostic HOUR extraction expression. */
    private function hourExpr(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'oracle' => "TO_CHAR({$column}, 'HH24')",
            'mysql'  => "HOUR({$column})",
            default  => "strftime('%H', {$column})", // sqlite
        };
    }

    private function variation(float $current, float $previous): ?float
    {
        if ($previous == 0.0) {
            return $current > 0 ? 100.0 : null;
        }
        return round(($current - $previous) / $previous * 100, 1);
    }

    // ── BLOC 1 — KPIs principaux ────────────────────────────────────────────────
    public function kpis(Request $request): JsonResponse
    {
        if (! $this->authorize()) return $this->denied();

        $p = $this->resolvePeriod($request->get('periode', 'today'));

        $current  = $this->ventesAggregat($p['start'], $p['end']);
        $previous = $this->ventesAggregat($p['prevStart'], $p['prevEnd']);

        $nouveauxClients = Client::whereBetween('created_at', [$p['start'], $p['end']])->count();
        $totalClients    = Client::count();

        return response()->json([
            'ca'               => $current['ca'],
            'ca_variation'     => $this->variation($current['ca'], $previous['ca']),
            'nb_ventes'        => $current['nb'],
            'nb_ventes_variation' => $this->variation((float) $current['nb'], (float) $previous['nb']),
            'ticket_moyen'     => $current['ticket_moyen'],
            'ticket_moyen_variation' => $this->variation($current['ticket_moyen'], $previous['ticket_moyen']),
            'nouveaux_clients' => $nouveauxClients,
            'total_clients'    => $totalClients,
        ]);
    }

    private function ventesAggregat(Carbon $start, Carbon $end): array
    {
        $row = Sale::where('statut', '!=', Sale::STATUT_ANNULEE)
            ->whereBetween('date_vente', [$start, $end])
            ->selectRaw('COUNT(*) as nb, COALESCE(SUM(total_ttc), 0) as ca')
            ->first();

        $nb = (int) $row->nb;
        $ca = round((float) $row->ca, 3);

        return ['nb' => $nb, 'ca' => $ca, 'ticket_moyen' => $nb > 0 ? round($ca / $nb, 3) : 0.0];
    }

    // ── BLOC 2 — CA par jour (courbe actuelle vs précédente) ───────────────────
    public function caParJour(Request $request): JsonResponse
    {
        if (! $this->authorize()) return $this->denied();

        $p       = $this->resolvePeriod($request->get('periode', 'week'));
        $dateExp = $this->dateExpr('date_vente');

        $currentRows = Sale::where('statut', '!=', Sale::STATUT_ANNULEE)
            ->whereBetween('date_vente', [$p['start'], $p['end']])
            ->select(DB::raw("$dateExp as jour"), DB::raw('SUM(total_ttc) as ca'))
            ->groupBy(DB::raw($dateExp))
            ->pluck('ca', 'jour');

        $previousRows = Sale::where('statut', '!=', Sale::STATUT_ANNULEE)
            ->whereBetween('date_vente', [$p['prevStart'], $p['prevEnd']])
            ->select(DB::raw("$dateExp as jour"), DB::raw('SUM(total_ttc) as ca'))
            ->groupBy(DB::raw($dateExp))
            ->pluck('ca', 'jour');

        $currentDates  = CarbonPeriod::create($p['start'], $p['end']);
        $previousDates = CarbonPeriod::create($p['prevStart'], $p['prevEnd']);

        $labels   = [];
        $current  = [];
        $previous = [];

        $prevArr = iterator_to_array($previousDates);

        foreach ($currentDates as $i => $day) {
            $labels[]  = $day->format('d/m');
            $current[] = round((float) ($currentRows[$day->format('Y-m-d')] ?? 0), 3);
            $prevDay   = $prevArr[$i] ?? null;
            $previous[] = $prevDay ? round((float) ($previousRows[$prevDay->format('Y-m-d')] ?? 0), 3) : 0.0;
        }

        return response()->json(['labels' => $labels, 'current' => $current, 'previous' => $previous]);
    }

    // ── BLOC 3 — Top 10 produits vendus (par CA) ────────────────────────────────
    public function topProduits(Request $request): JsonResponse
    {
        if (! $this->authorize()) return $this->denied();

        $p = $this->resolvePeriod($request->get('periode', 'today'));

        $rows = Sale::where('sales.statut', '!=', Sale::STATUT_ANNULEE)
            ->whereBetween('sales.date_vente', [$p['start'], $p['end']])
            ->join('sale_items', 'sale_items.sale_id', '=', 'sales.id')
            ->groupBy('sale_items.designation')
            ->selectRaw('sale_items.designation as nom')
            ->selectRaw('SUM(sale_items.quantite) as qte')
            ->selectRaw('SUM(sale_items.total_ligne_ttc) as ca')
            ->orderByDesc('ca')
            ->limit(10)
            ->get()
            ->map(fn($r) => [
                'nom' => $r->nom,
                'qte' => (float) $r->qte,
                'ca'  => round((float) $r->ca, 3),
            ]);

        return response()->json($rows);
    }

    // ── BLOC 4 — Répartition des paiements ──────────────────────────────────────
    public function paiements(Request $request): JsonResponse
    {
        if (! $this->authorize()) return $this->denied();

        $p = $this->resolvePeriod($request->get('periode', 'today'));

        $rows = Sale::where('statut', '!=', Sale::STATUT_ANNULEE)
            ->whereBetween('date_vente', [$p['start'], $p['end']])
            ->groupBy('mode_paiement')
            ->selectRaw('mode_paiement, COUNT(*) as nb, SUM(total_ttc) as montant')
            ->get();

        $total = (float) $rows->sum('montant');

        $result = $rows->map(fn($r) => [
            'mode'       => $r->mode_paiement,
            'montant'    => round((float) $r->montant, 3),
            'nb'         => (int) $r->nb,
            'pourcentage' => $total > 0 ? round((float) $r->montant / $total * 100, 1) : 0,
        ]);

        return response()->json($result);
    }

    // ── BLOC 5 — Clients avec ardoise ────────────────────────────────────────────
    public function clientsArdoise(): JsonResponse
    {
        if (! $this->authorize()) return $this->denied();

        $soldeExpr = "(SELECT COALESCE(SUM(s.total_ttc - s.montant_regle), 0)
                       FROM sales s
                       WHERE s.client_id = clients.id
                         AND s.organisation_id = clients.organisation_id
                         AND s.statut != 'annulee')";
        $derniereVenteExpr = "(SELECT MAX(s.date_vente)
                       FROM sales s
                       WHERE s.client_id = clients.id
                         AND s.organisation_id = clients.organisation_id
                         AND s.statut != 'annulee')";

        $clients = Client::query()
            ->select('clients.id', 'clients.nom')
            ->selectRaw("$soldeExpr as solde")
            ->selectRaw("$derniereVenteExpr as derniere_vente")
            ->whereRaw("$soldeExpr > 0")
            ->orderByDesc('solde')
            ->limit(5)
            ->get()
            ->map(fn($c) => [
                'id'              => $c->id,
                'nom'             => $c->nom,
                'solde'           => round((float) $c->solde, 3),
                'derniere_vente'  => $c->derniere_vente,
            ]);

        return response()->json($clients);
    }

    // ── BLOC 6 (restauration) — CA par service ──────────────────────────────────
    public function caParService(Request $request): JsonResponse
    {
        if (! $this->authorize()) return $this->denied();

        $p        = $this->resolvePeriod($request->get('periode', 'today'));
        $hourExp  = $this->hourExpr('date_vente');

        $rows = Sale::where('statut', '!=', Sale::STATUT_ANNULEE)
            ->whereBetween('date_vente', [$p['start'], $p['end']])
            ->select(DB::raw("$hourExp as heure"), DB::raw('SUM(total_ttc) as ca'), DB::raw('COUNT(*) as nb'))
            ->groupBy(DB::raw($hourExp))
            ->get();

        $buckets = [
            'midi'  => ['ca' => 0.0, 'nb' => 0],
            'soir'  => ['ca' => 0.0, 'nb' => 0],
            'autre' => ['ca' => 0.0, 'nb' => 0],
        ];

        foreach ($rows as $r) {
            $h = (int) $r->heure;
            $key = ($h >= 11 && $h < 15) ? 'midi' : (($h >= 18 && $h < 23) ? 'soir' : 'autre');
            $buckets[$key]['ca'] += (float) $r->ca;
            $buckets[$key]['nb'] += (int) $r->nb;
        }

        return response()->json([
            'midi'  => ['ca' => round($buckets['midi']['ca'], 3),  'nb' => $buckets['midi']['nb']],
            'soir'  => ['ca' => round($buckets['soir']['ca'], 3),  'nb' => $buckets['soir']['nb']],
            'autre' => ['ca' => round($buckets['autre']['ca'], 3), 'nb' => $buckets['autre']['nb']],
        ]);
    }

    // ── BLOC 7 (restauration) — Heures de pointe ────────────────────────────────
    public function heuresPointe(Request $request): JsonResponse
    {
        if (! $this->authorize()) return $this->denied();

        $p       = $this->resolvePeriod($request->get('periode', 'today'));
        $hourExp = $this->hourExpr('date_vente');

        $rows = Sale::where('statut', '!=', Sale::STATUT_ANNULEE)
            ->whereBetween('date_vente', [$p['start'], $p['end']])
            ->select(DB::raw("$hourExp as heure"), DB::raw('SUM(total_ttc) as ca'))
            ->groupBy(DB::raw($hourExp))
            ->get()
            ->keyBy(fn($r) => (int) $r->heure);

        $totalCa = (float) $rows->sum('ca');
        $heures  = [];
        for ($h = 8; $h <= 23; $h++) {
            $ca = round((float) ($rows[$h]->ca ?? 0), 3);
            $heures[] = [
                'heure'       => sprintf('%02dh', $h),
                'ca'          => $ca,
                'pourcentage' => $totalCa > 0 ? round($ca / $totalCa * 100, 1) : 0,
            ];
        }

        $pointe = collect($heures)->sortByDesc('ca')->first();

        return response()->json(['heures' => $heures, 'heure_pointe' => $pointe]);
    }

    // ── BLOC 8 (restauration) — Food cost global ────────────────────────────────
    public function foodCost(Request $request): JsonResponse
    {
        if (! $this->authorize()) return $this->denied();

        $p = $this->resolvePeriod($request->get('periode', 'today'));

        $rows = Sale::where('sales.statut', '!=', Sale::STATUT_ANNULEE)
            ->whereBetween('sales.date_vente', [$p['start'], $p['end']])
            ->join('sale_items', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->where('products.type', Product::TYPE_COMPOSE)
            ->groupBy('sale_items.product_id', 'products.nom')
            ->selectRaw('products.nom as nom')
            ->selectRaw('SUM(sale_items.prix_achat_unitaire * sale_items.quantite) as cout')
            ->selectRaw('SUM(sale_items.prix_unitaire_ht * sale_items.quantite) as revenu')
            ->get();

        $coutTotal   = (float) $rows->sum('cout');
        $revenuTotal = (float) $rows->sum('revenu');
        $globalPct   = $revenuTotal > 0 ? round($coutTotal / $revenuTotal * 100, 1) : null;

        $top3 = $rows->map(fn($r) => [
            'nom'        => $r->nom,
            'food_cost'  => (float) $r->revenu > 0 ? round((float) $r->cout / (float) $r->revenu * 100, 1) : 0,
        ])->sortByDesc('food_cost')->take(3)->values();

        return response()->json([
            'food_cost_global' => $globalPct,
            'top3_a_surveiller' => $top3,
        ]);
    }

    // ── BLOC 9 (restauration) — Tables ───────────────────────────────────────────
    public function tables(Request $request): JsonResponse
    {
        if (! $this->authorize()) return $this->denied();

        $p = $this->resolvePeriod($request->get('periode', 'today'));

        $totalTables    = RestaurantTable::where('active', true)->count();
        $tablesOccupees = RestaurantTable::where('active', true)->where('statut', RestaurantTable::STATUT_OCCUPEE)->count();
        $tauxOccupation = $totalTables > 0 ? round($tablesOccupees / $totalTables * 100, 1) : 0;

        $diffExpr = DB::connection()->getDriverName() === 'sqlite'
            ? '(julianday(updated_at) - julianday(created_at)) * 24 * 60'
            : 'TIMESTAMPDIFF(MINUTE, created_at, updated_at)';

        $dureeMoyenne = Order::where('statut', Order::STATUT_PAYEE)
            ->whereBetween('created_at', [$p['start'], $p['end']])
            ->selectRaw("AVG($diffExpr) as duree")
            ->value('duree');

        $tableRentable = Sale::where('sales.statut', '!=', Sale::STATUT_ANNULEE)
            ->whereNotNull('sales.table_id')
            ->whereBetween('sales.date_vente', [$p['start'], $p['end']])
            ->join('tables_restaurant', 'sales.table_id', '=', 'tables_restaurant.id')
            ->groupBy('sales.table_id', 'tables_restaurant.numero')
            ->selectRaw('tables_restaurant.numero as numero, SUM(sales.total_ttc) as ca')
            ->orderByDesc('ca')
            ->first();

        return response()->json([
            'taux_occupation'     => $tauxOccupation,
            'tables_occupees'     => $tablesOccupees,
            'tables_total'        => $totalTables,
            'duree_moyenne_min'   => $dureeMoyenne ? round((float) $dureeMoyenne, 0) : null,
            'table_plus_rentable' => $tableRentable
                ? ['numero' => $tableRentable->numero, 'ca' => round((float) $tableRentable->ca, 3)]
                : null,
        ]);
    }

    // ── BLOC 6 (commerce) — Ventes détail vs gros ───────────────────────────────
    public function ventesDetailGros(Request $request): JsonResponse
    {
        if (! $this->authorize()) return $this->denied();

        $p       = $this->resolvePeriod($request->get('periode', 'week'));
        $dateExp = $this->dateExpr('sales.date_vente');

        $rows = Sale::where('sales.statut', '!=', Sale::STATUT_ANNULEE)
            ->whereBetween('sales.date_vente', [$p['start'], $p['end']])
            ->join('sale_items', 'sale_items.sale_id', '=', 'sales.id')
            ->select(DB::raw("$dateExp as jour"), 'sale_items.type_prix')
            ->selectRaw('SUM(sale_items.total_ligne_ttc) as ca')
            ->groupBy(DB::raw($dateExp), 'sale_items.type_prix')
            ->get();

        $labels = [];
        $detail = [];
        $gros   = [];

        foreach (CarbonPeriod::create($p['start'], $p['end']) as $day) {
            $key = $day->format('Y-m-d');
            $labels[] = $day->format('d/m');
            $detail[] = round((float) $rows->first(fn($r) => $r->jour === $key && $r->type_prix === 'detail')?->ca ?? 0, 3);
            $gros[]   = round((float) $rows->first(fn($r) => $r->jour === $key && $r->type_prix === 'gros')?->ca ?? 0, 3);
        }

        return response()->json(['labels' => $labels, 'detail' => $detail, 'gros' => $gros]);
    }

    // ── BLOC 7 (commerce) — Rotation des stocks ─────────────────────────────────
    public function rotationStocks(): JsonResponse
    {
        if (! $this->authorize()) return $this->denied();

        $depuis = now()->subDays(30);

        $ventes = Sale::where('sales.statut', '!=', Sale::STATUT_ANNULEE)
            ->where('sales.date_vente', '>=', $depuis)
            ->join('sale_items', 'sale_items.sale_id', '=', 'sales.id')
            ->groupBy('sale_items.product_id')
            ->selectRaw('sale_items.product_id, SUM(sale_items.quantite) as qte_vendue')
            ->pluck('qte_vendue', 'sale_items.product_id');

        $produits = Product::where('actif', true)
            ->where('type', Product::TYPE_SIMPLE)
            ->where('quantite', '>', 0)
            ->get(['id', 'nom', 'quantite']);

        $rotation = $produits->map(function ($prod) use ($ventes) {
            $qteVendue = (float) ($ventes[$prod->id] ?? 0);
            $vitesseJour = $qteVendue / 30;
            $joursEcoulement = $vitesseJour > 0 ? round((float) $prod->quantite / $vitesseJour, 1) : null;

            return [
                'nom'              => $prod->nom,
                'stock_actuel'     => (float) $prod->quantite,
                'qte_vendue_30j'   => $qteVendue,
                'jours_ecoulement' => $joursEcoulement,
            ];
        });

        $rapides = $rotation->filter(fn($r) => $r['jours_ecoulement'] !== null)
            ->sortBy('jours_ecoulement')->take(5)->values();
        $lentes = $rotation->sortBy(fn($r) => $r['jours_ecoulement'] ?? PHP_INT_MAX, SORT_REGULAR, true)
            ->take(5)->values();

        return response()->json(['rotation_rapide' => $rapides, 'rotation_lente' => $lentes]);
    }

    // ── BLOC 8 (commerce) — Marge brute par produit ─────────────────────────────
    public function margeProduits(Request $request): JsonResponse
    {
        if (! $this->authorize()) return $this->denied();

        $p = $this->resolvePeriod($request->get('periode', 'today'));

        $rows = Sale::where('sales.statut', '!=', Sale::STATUT_ANNULEE)
            ->whereBetween('sales.date_vente', [$p['start'], $p['end']])
            ->join('sale_items', 'sale_items.sale_id', '=', 'sales.id')
            ->groupBy('sale_items.designation')
            ->selectRaw('sale_items.designation as nom')
            ->selectRaw('SUM(sale_items.prix_unitaire_ht * sale_items.quantite) as revenu')
            ->selectRaw('SUM(sale_items.prix_achat_unitaire * sale_items.quantite) as cout')
            ->orderByDesc(DB::raw('SUM(sale_items.prix_unitaire_ht * sale_items.quantite) - SUM(sale_items.prix_achat_unitaire * sale_items.quantite)'))
            ->limit(10)
            ->get()
            ->map(function ($r) {
                $revenu = (float) $r->revenu;
                $cout   = (float) $r->cout;
                $marge  = round($revenu - $cout, 3);
                return [
                    'nom'        => $r->nom,
                    'marge_dt'   => $marge,
                    'marge_pct'  => $revenu > 0 ? round($marge / $revenu * 100, 1) : 0,
                ];
            });

        return response()->json($rows);
    }

    // ── Clients fidèles (commerce) — Top 5 par CA sur la période ────────────────
    public function clientsFideles(Request $request): JsonResponse
    {
        if (! $this->authorize()) return $this->denied();

        $p = $this->resolvePeriod($request->get('periode', 'month'));

        $rows = Sale::where('sales.statut', '!=', Sale::STATUT_ANNULEE)
            ->whereNotNull('sales.client_id')
            ->whereBetween('sales.date_vente', [$p['start'], $p['end']])
            ->join('clients', 'sales.client_id', '=', 'clients.id')
            ->groupBy('clients.id', 'clients.nom')
            ->selectRaw('clients.id, clients.nom')
            ->selectRaw('COUNT(sales.id) as nb_achats')
            ->selectRaw('SUM(sales.total_ttc) as ca_total')
            ->selectRaw('MAX(sales.date_vente) as derniere_visite')
            ->orderByDesc('ca_total')
            ->limit(5)
            ->get()
            ->map(fn($r) => [
                'id'               => $r->id,
                'nom'              => $r->nom,
                'nb_achats'        => (int) $r->nb_achats,
                'ca_total'         => round((float) $r->ca_total, 3),
                'derniere_visite'  => $r->derniere_visite,
            ]);

        return response()->json($rows);
    }
}
