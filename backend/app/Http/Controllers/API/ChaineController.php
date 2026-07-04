<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PointDeVente;
use App\Models\Sale;
use App\Models\StockParPoint;
use App\Models\Transfert;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ChaineController extends Controller
{
    private function guardAdmin(): ?JsonResponse
    {
        $role = app('current_user')->role;
        if (! in_array($role, ['admin', 'super_admin'])) {
            return response()->json(['message' => 'Accès réservé aux administrateurs.'], 403);
        }
        return null;
    }

    // ── CA par point de vente ──────────────────────────────────────────────────
    public function caParPoint(): JsonResponse
    {
        if ($guard = $this->guardAdmin()) return $guard;

        $today    = Carbon::today();
        $yesterday = Carbon::yesterday();
        $sowk     = Carbon::now()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $eowk     = Carbon::now()->endOfWeek(Carbon::SUNDAY)->endOfDay();
        $sowkPrev = (clone $sowk)->subWeek();
        $eowkPrev = (clone $eowk)->subWeek();

        // Points de vente uniquement (pas entrepôts)
        $pdvs = PointDeVente::where('type', 'point_vente')
            ->where('actif', true)
            ->orderBy('nom')
            ->get();

        $points            = [];
        $caTotalJour       = 0.0;
        $caTotalHier       = 0.0;
        $nbVentesTotalJour = 0;
        $repartition       = [];

        foreach ($pdvs as $pdv) {
            // Base query — TenantScope auto-injecté
            $base = fn() => Sale::where('point_de_vente_id', $pdv->id)
                ->where('statut', '!=', 'annulee');

            $caJour   = (float) $base()->whereDate('date_vente', $today)->sum('total_ttc');
            $caHier   = (float) $base()->whereDate('date_vente', $yesterday)->sum('total_ttc');
            $caSem    = (float) $base()->whereBetween('date_vente', [$sowk, $eowk])->sum('total_ttc');
            $caSemPrv = (float) $base()->whereBetween('date_vente', [$sowkPrev, $eowkPrev])->sum('total_ttc');
            $nbJour   = (int)   $base()->whereDate('date_vente', $today)->count();
            $nbSem    = (int)   $base()->whereBetween('date_vente', [$sowk, $eowk])->count();

            $caTotalJour       += $caJour;
            $caTotalHier       += $caHier;
            $nbVentesTotalJour += $nbJour;
            $repartition[]      = ['nom' => $pdv->nom, 'nb_ventes' => $nbJour];

            $points[] = [
                'id'                    => $pdv->id,
                'nom'                   => $pdv->nom,
                'ca_jour'               => round($caJour, 3),
                'ca_hier'               => round($caHier, 3),
                'ca_semaine'            => round($caSem, 3),
                'ca_semaine_precedente' => round($caSemPrv, 3),
                'nb_ventes_jour'        => $nbJour,
                'nb_ventes_semaine'     => $nbSem,
            ];
        }

        // Delta : null si ca_hier = 0 (évite la division par zéro)
        $delta = $caTotalHier > 0
            ? round(($caTotalJour - $caTotalHier) / $caTotalHier * 100, 1)
            : null;

        return response()->json([
            'summary' => [
                'ca_total_jour'        => round($caTotalJour, 3),
                'ca_total_hier'        => round($caTotalHier, 3),
                'delta_pct'            => $delta,
                'nb_ventes_total_jour' => $nbVentesTotalJour,
                'repartition'          => $repartition,
            ],
            'points' => $points,
        ]);
    }

    // ── Stock par point de vente ───────────────────────────────────────────────
    public function stockParPoint(): JsonResponse
    {
        if ($guard = $this->guardAdmin()) return $guard;

        $showAll = request()->boolean('show_all');

        // Tous les PDVs actifs (entrepôt en premier)
        $pdvs = PointDeVente::where('actif', true)
            ->orderByRaw("CASE WHEN type = 'entrepot' THEN 0 ELSE 1 END")
            ->orderBy('nom')
            ->get(['id', 'nom', 'type']);

        // Lignes stock avec infos produit
        $stockRows = StockParPoint::whereIn('point_de_vente_id', $pdvs->pluck('id'))
            ->with('product:id,nom,unite_mesure,seuil_alerte,type')
            ->get();

        // Grouper par produit
        $byProduct = $stockRows->groupBy('product_id');

        $produits = $byProduct->map(function ($rows) {
            $product = $rows->first()->product;
            if (! $product) return null;

            $seuil    = (float) $product->seuil_alerte;
            $stock    = [];
            $hasAlert = false;

            foreach ($rows as $row) {
                $qty = (float) $row->quantite;

                if ($qty === 0.0) {
                    $statut   = 'rupture';
                    $hasAlert = true;
                } elseif ($seuil > 0 && $qty <= $seuil) {
                    $statut   = 'alerte';
                    $hasAlert = true;
                } else {
                    $statut = 'ok';
                }

                $stock[$row->point_de_vente_id] = [
                    'quantite' => $qty,
                    'statut'   => $statut,
                ];
            }

            return [
                'id'           => $product->id,
                'nom'          => $product->nom,
                'unite'        => $product->unite_mesure,
                'seuil_alerte' => $seuil,
                'stock'        => $stock,
                'has_alert'    => $hasAlert,
            ];
        })->filter()->values();

        // Filtrer sur les produits en alerte/rupture (sauf show_all)
        if (! $showAll) {
            $produits = $produits->filter(fn($p) => $p['has_alert'])->values();
        }

        // Nombre d'alertes entrepôt pour la carte récap
        $entrepot             = $pdvs->firstWhere('type', 'entrepot');
        $entrepotAlertesCount = 0;
        if ($entrepot) {
            $entrepotAlertesCount = $produits->filter(function ($p) use ($entrepot) {
                $s = $p['stock'][$entrepot->id] ?? null;
                return $s && in_array($s['statut'], ['alerte', 'rupture']);
            })->count();
        }

        return response()->json([
            'points'                 => $pdvs,
            'produits'               => $produits,
            'entrepot_alertes_count' => $entrepotAlertesCount,
        ]);
    }

    // ── Top 5 plats (produits composés) ──────────────────────────────────────
    public function topPlats(): JsonResponse
    {
        if ($guard = $this->guardAdmin()) return $guard;

        $orgId = app('current_organisation_id');
        $today = Carbon::today();
        $sowk  = Carbon::now()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $eowk  = Carbon::now()->endOfWeek(Carbon::SUNDAY)->endOfDay();

        $buildTop = function (string $period) use ($orgId, $today, $sowk, $eowk): array {
            $query = DB::table('sale_items')
                ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                ->join('products', 'products.id', '=', 'sale_items.product_id')
                ->leftJoin('points_de_vente', 'points_de_vente.id', '=', 'sales.point_de_vente_id')
                ->where('sales.organisation_id', $orgId)
                ->where('products.type', 'compose')
                ->where('sales.statut', '!=', 'annulee');

            if ($period === 'jour') {
                $query->whereDate('sales.date_vente', $today);
            } else {
                $query->whereBetween('sales.date_vente', [$sowk, $eowk]);
            }

            $rows = $query
                ->groupBy(
                    'sale_items.product_id', 'products.nom',
                    'sales.point_de_vente_id', 'points_de_vente.nom'
                )
                ->select(
                    'sale_items.product_id',
                    'products.nom as product_nom',
                    'sales.point_de_vente_id',
                    'points_de_vente.nom as pdv_nom',
                    DB::raw('SUM(sale_items.quantite) as vendu')
                )
                ->get();

            return $rows->groupBy('product_id')
                ->map(fn($pdvRows, $productId) => [
                    'product_id' => $productId,
                    'nom'        => $pdvRows->first()->product_nom,
                    'total'      => (int) $pdvRows->sum('vendu'),
                    'par_point'  => $pdvRows->map(fn($r) => [
                        'point_id' => $r->point_de_vente_id,
                        'nom'      => $r->pdv_nom ?? '—',
                        'vendu'    => (int) $r->vendu,
                    ])->values()->toArray(),
                ])
                ->sortByDesc('total')
                ->take(5)
                ->values()
                ->toArray();
        };

        return response()->json([
            'top_jour'    => $buildTop('jour'),
            'top_semaine' => $buildTop('semaine'),
        ]);
    }

    // ── Transferts récents ────────────────────────────────────────────────────
    public function transfertsRecents(): JsonResponse
    {
        if ($guard = $this->guardAdmin()) return $guard;

        $transferts = Transfert::with([
            'pointSource:id,nom',
            'pointDest:id,nom',
            'createdBy:id,nom,prenom',
        ])
        ->withCount('items')
        ->latest()
        ->limit(5)
        ->get();

        return response()->json($transferts);
    }
}
