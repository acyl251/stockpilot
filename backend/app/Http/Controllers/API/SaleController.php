<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Sale;
use App\Services\InvoiceService;
use App\Services\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SaleController extends Controller
{
    public function __construct(
        private SaleService $saleService,
        private InvoiceService $invoiceService,
    ) {}

    /** Facture PDF légale d'une vente (numéro attribué à la 1re génération). */
    public function invoice(int $id)
    {
        $sale = Sale::with(['items', 'client', 'user'])->findOrFail($id);
        $pdf  = $this->invoiceService->pdf($sale);

        return $pdf->download("facture_{$sale->numero_facture}.pdf");
    }

    public function index(Request $request): JsonResponse
    {
        $filtered = fn($q) => $q
            ->when($request->date_from, fn($q, $d) => $q->whereDate('date_vente', '>=', $d))
            ->when($request->date_to, fn($q, $d) => $q->whereDate('date_vente', '<=', $d));

        // Aggregated totals for the current filter — CA excludes cancelled sales.
        $summary = $filtered(Sale::query())
            ->selectRaw("COUNT(*) as nb_ventes")
            ->selectRaw("SUM(CASE WHEN statut = 'annulee' THEN 1 ELSE 0 END) as nb_annulees")
            ->selectRaw("COALESCE(SUM(CASE WHEN statut != 'annulee' THEN total_ttc ELSE 0 END), 0) as ca_ttc")
            ->first();

        $paginator = $filtered(Sale::with(['user:id,nom,prenom', 'client:id,nom'])->withCount('items'))
            ->latest('date_vente')
            ->paginate($request->per_page ?? 25);

        // Paginators have no ->additional(); merge the summary into the payload.
        $payload = $paginator->toArray();
        $payload['summary'] = [
            'nb_ventes'   => (int) $summary->nb_ventes,
            'nb_annulees' => (int) $summary->nb_annulees,
            'ca_ttc'      => (float) $summary->ca_ttc,
        ];

        return response()->json($payload);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items'                => 'required|array|min:1',
            'items.*.product_id'   => 'required|integer',
            'items.*.quantite'     => 'required|numeric|min:0.001',
            'mode_paiement'        => 'required|in:especes,carte,credit',
            'montant_paye'         => 'nullable|numeric|min:0',
            'remise_type'          => 'nullable|in:pourcentage,montant',
            'remise_valeur'        => 'nullable|numeric|min:0',
            'client_id'            => 'nullable|integer|exists:clients,id',
            'client_nom'           => 'nullable|string|max:150',
            'client_telephone'     => 'nullable|string|max:30',
        ]);

        // Vente à crédit : résoudre le client (existant ou créé à la volée).
        $clientId = $data['client_id'] ?? null;
        if ($data['mode_paiement'] === 'credit' && ! $clientId && ! empty($data['client_nom'])) {
            $clientId = Client::create([
                'nom'       => $data['client_nom'],
                'telephone' => $data['client_telephone'] ?? null,
            ])->id;
        }

        $sale = $this->saleService->createSale(
            items:        $data['items'],
            userId:       app('current_user')->id,
            modePaiement: $data['mode_paiement'],
            montantPaye:  $data['montant_paye'] ?? null,
            remiseType:   $data['remise_type'] ?? null,
            remiseValeur: $data['remise_valeur'] ?? null,
            clientId:     $clientId,
        );

        return response()->json(
            $sale->load(['items.product:id,nom,reference', 'user:id,nom,prenom', 'client:id,nom,telephone']),
            201,
        );
    }

    public function show(int $id): JsonResponse
    {
        $sale = Sale::with(['items.product:id,nom,reference', 'user:id,nom,prenom', 'client:id,nom,telephone'])
            ->findOrFail($id);

        return response()->json($sale);
    }

    public function cancel(int $id): JsonResponse
    {
        $sale = $this->saleService->cancelSale($id, app('current_user')->id);

        return response()->json($sale->load(['items.product:id,nom,reference', 'user:id,nom,prenom']));
    }

    /**
     * Export the filtered sales as a CSV cash report (rapport Z).
     */
    public function export(Request $request): StreamedResponse
    {
        $sales = Sale::with('user:id,nom,prenom')
            ->when($request->date_from, fn($q, $d) => $q->whereDate('date_vente', '>=', $d))
            ->when($request->date_to, fn($q, $d) => $q->whereDate('date_vente', '<=', $d))
            ->latest('date_vente')
            ->get();

        $filename = 'ventes_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($sales) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM so Excel reads accents correctly.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Numero', 'Date', 'Vendeur', 'Total HT', 'TVA', 'Remise', 'Total TTC', 'Paiement', 'Statut'], ';');

            foreach ($sales as $s) {
                fputcsv($out, [
                    $s->numero,
                    optional($s->date_vente)->format('Y-m-d H:i'),
                    $s->user ? $s->user->prenom . ' ' . $s->user->nom : '',
                    number_format((float) $s->total_ht, 3, '.', ''),
                    number_format((float) $s->total_tva, 3, '.', ''),
                    number_format((float) $s->remise_montant, 3, '.', ''),
                    number_format((float) $s->total_ttc, 3, '.', ''),
                    $s->mode_paiement,
                    $s->statut,
                ], ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
