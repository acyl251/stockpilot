<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Organisation;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConsommationController extends Controller
{
    private function requireRestauration(): ?JsonResponse
    {
        $org = Organisation::findOrFail(app('current_organisation_id'));
        if (! $org->isRestauration()) {
            return response()->json(['message' => 'Réservé au secteur restauration.'], 403);
        }
        return null;
    }

    public function index(Request $request): JsonResponse
    {
        if ($err = $this->requireRestauration()) return $err;

        $debut = $request->debut
            ? Carbon::parse($request->debut)->startOfDay()
            : now()->startOfDay();

        $fin = $request->fin
            ? Carbon::parse($request->fin)->endOfDay()
            : now()->endOfDay();

        $rows = StockMovement::where('stock_movements.type_mouvement', 'sortie')
            ->whereBetween('stock_movements.date_mouvement', [$debut, $fin])
            ->join('products', 'stock_movements.product_id', '=', 'products.id')
            ->where('products.type', '!=', 'compose')
            ->groupBy('stock_movements.product_id', 'products.nom', 'products.unite_mesure', 'products.prix_achat_ht')
            ->selectRaw('products.nom, products.unite_mesure, products.prix_achat_ht, SUM(stock_movements.quantite) as consomme')
            ->orderByDesc('consomme')
            ->get()
            ->map(fn ($r) => [
                'nom'        => $r->nom,
                'unite'      => $r->unite_mesure,
                'consomme'   => round((float) $r->consomme, 3),
                'cout_total' => round((float) $r->consomme * (float) $r->prix_achat_ht, 3),
            ]);

        $coutTotal   = round($rows->sum('cout_total'), 3);
        $plusConso   = $rows->first();
        $plusCouteux = $rows->sortByDesc('cout_total')->first();

        return response()->json([
            'data'   => $rows->values(),
            'resume' => [
                'cout_total'    => $coutTotal,
                'plus_consomme' => $plusConso,
                'plus_couteux'  => $plusCouteux,
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        if ($err = $this->requireRestauration()) {
            // StreamedResponse can't return JSON — just send 403 headers
            abort(403, 'Réservé au secteur restauration.');
        }

        $debut = $request->debut ? Carbon::parse($request->debut)->startOfDay() : now()->startOfDay();
        $fin   = $request->fin   ? Carbon::parse($request->fin)->endOfDay()     : now()->endOfDay();

        $rows = StockMovement::where('stock_movements.type_mouvement', 'sortie')
            ->whereBetween('stock_movements.date_mouvement', [$debut, $fin])
            ->join('products', 'stock_movements.product_id', '=', 'products.id')
            ->where('products.type', '!=', 'compose')
            ->groupBy('stock_movements.product_id', 'products.nom', 'products.unite_mesure', 'products.prix_achat_ht')
            ->selectRaw('products.nom, products.unite_mesure, products.prix_achat_ht, SUM(stock_movements.quantite) as consomme')
            ->orderByDesc('consomme')
            ->get();

        $filename = 'consommation_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM
            fputcsv($out, ['Ingrédient', 'Unité', 'Consommé', 'Coût total matière (TND)'], ';');
            foreach ($rows as $r) {
                $consomme  = round((float) $r->consomme, 3);
                $coutTotal = round($consomme * (float) $r->prix_achat_ht, 3);
                fputcsv($out, [
                    $r->nom,
                    $r->unite_mesure,
                    number_format($consomme, 3, '.', ''),
                    number_format($coutTotal, 3, '.', ''),
                ], ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
