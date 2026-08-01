<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\CommandeClient;
use App\Models\CommandeClientItem;
use App\Models\Product;
use App\Services\ActivityLogService;
use App\Services\SaleService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommandeClientController extends Controller
{
    public function __construct(private SaleService $saleService) {}

    public function index(Request $request): JsonResponse
    {
        $query = CommandeClient::with(['client:id,nom,telephone', 'items'])
            ->orderByDesc('created_at');

        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        return response()->json($query->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        if ($this->isRestrictedOperateur()) {
            return response()->json(['message' => 'Action non autorisée dans une organisation multi-points de vente.'], 403);
        }

        $data = $request->validate([
            'client_id'          => 'nullable|integer|exists:clients,id',
            'nom_client'         => 'required_without:client_id|nullable|string|max:150',
            'telephone_client'   => 'nullable|string|max:30',
            'adresse_livraison'  => 'nullable|string|max:500',
            'note'               => 'nullable|string',
            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id,actif,1',
            'items.*.quantite'   => 'required|numeric|min:0.001',
            'items.*.type_prix'  => 'nullable|in:detail,gros',
        ]);

        $commande = DB::transaction(function () use ($data) {
            $totalTtc = 0.0;
            $lines    = [];

            foreach ($data['items'] as $row) {
                $product  = Product::findOrFail($row['product_id']);
                $typePrix = $row['type_prix'] ?? 'detail';
                $prixUnitaire = $typePrix === 'gros' && $product->prix_vente_gros
                    ? (float) $product->prix_vente_gros
                    : (float) $product->prix_vente_ht;

                $quantite = (float) $row['quantite'];
                $total    = round($prixUnitaire * $quantite, 3);
                $totalTtc += $total;

                $lines[] = [
                    'product_id'    => $product->id,
                    'quantite'      => $quantite,
                    'prix_unitaire' => $prixUnitaire,
                    'type_prix'     => $typePrix,
                    'total'         => $total,
                ];
            }

            $commande = CommandeClient::create([
                'client_id'         => $data['client_id'] ?? null,
                'nom_client'        => $data['nom_client'] ?? null,
                'telephone_client'  => $data['telephone_client'] ?? null,
                'adresse_livraison' => $data['adresse_livraison'] ?? null,
                'note'              => $data['note'] ?? null,
                'numero_bon'        => $this->nextNumeroBon(),
                'total_ttc'         => round($totalTtc, 3),
                'statut'            => CommandeClient::STATUT_EN_PREPARATION,
            ]);

            foreach ($lines as $line) {
                CommandeClientItem::create([
                    'commande_client_id' => $commande->id,
                    ...$line,
                ]);
            }

            return $commande;
        });

        ActivityLogService::log('created', 'commande_client',
            "Commande client {$commande->numero_bon} créée — " . number_format((float) $commande->total_ttc, 3, '.', '') . ' TND',
            ['commande_id' => $commande->id]
        );

        return response()->json(
            $commande->load(['client:id,nom,telephone', 'items.product:id,nom,unite_mesure']),
            201
        );
    }

    public function show(int $id): JsonResponse
    {
        $commande = CommandeClient::with([
            'client', 'items.product:id,nom,reference,unite_mesure', 'sale',
        ])->findOrFail($id);

        return response()->json($commande);
    }

    /** PATCH /commandes-clients/{id}/statut — prete | livree | annulee */
    public function updateStatut(Request $request, int $id): JsonResponse
    {
        if ($this->isRestrictedOperateur()) {
            return response()->json(['message' => 'Action non autorisée dans une organisation multi-points de vente.'], 403);
        }

        $data = $request->validate([
            'statut' => 'required|in:prete,livree,annulee',
        ]);

        $commande = CommandeClient::findOrFail($id);

        if (in_array($commande->statut, [CommandeClient::STATUT_PAYEE, CommandeClient::STATUT_ANNULEE])) {
            return response()->json(['message' => 'Cette commande ne peut plus être modifiée.'], 422);
        }

        $commande->update(['statut' => $data['statut']]);

        ActivityLogService::log('updated', 'commande_client',
            "Commande {$commande->numero_bon} — statut : {$data['statut']}",
            ['commande_id' => $commande->id]
        );

        return response()->json($commande->fresh()->load(['client:id,nom,telephone', 'items.product:id,nom,unite_mesure']));
    }

    /** POST /commandes-clients/{id}/transformer-vente */
    public function transformerVente(Request $request, int $id): JsonResponse
    {
        if ($this->isRestrictedOperateur()) {
            return response()->json(['message' => 'Action non autorisée dans une organisation multi-points de vente.'], 403);
        }

        $data = $request->validate([
            'mode_paiement' => 'required|in:cash,credit',
        ]);

        $commande = CommandeClient::with('items')->findOrFail($id);

        if ($commande->statut !== CommandeClient::STATUT_LIVREE) {
            return response()->json(['message' => 'Seule une commande livrée peut être transformée en vente.'], 422);
        }

        $userId = app('current_user')->id;

        try {
            $sale = DB::transaction(function () use ($commande, $data, $userId) {
                $clientId = $commande->client_id;

                if ($data['mode_paiement'] === 'credit' && ! $clientId) {
                    if (empty($commande->nom_client)) {
                        throw ValidationException::withMessages([
                            'mode_paiement' => 'Un client est requis pour une vente à crédit.',
                        ]);
                    }
                    $clientId = Client::create([
                        'nom'       => $commande->nom_client,
                        'telephone' => $commande->telephone_client,
                    ])->id;
                }

                $items = $commande->items->map(fn($item) => [
                    'product_id' => $item->product_id,
                    'quantite'   => (float) $item->quantite,
                    'type_prix'  => $item->type_prix,
                ])->all();

                $sale = $this->saleService->createSale(
                    items:        $items,
                    userId:       $userId,
                    modePaiement: $data['mode_paiement'] === 'credit' ? 'credit' : 'especes',
                    montantPaye:  $data['mode_paiement'] === 'credit' ? 0 : (float) $commande->total_ttc,
                    clientId:     $clientId,
                );

                $commande->update([
                    'sale_id'       => $sale->id,
                    'client_id'     => $clientId,
                    'type_paiement' => $data['mode_paiement'],
                    'statut'        => CommandeClient::STATUT_PAYEE,
                ]);

                return $sale;
            });
        } catch (ValidationException $e) {
            throw $e;
        }

        $sale->load(['items.product:id,nom,reference', 'user:id,nom,prenom', 'client:id,nom,telephone']);

        ActivityLogService::log('sold', 'commande_client',
            "Commande {$commande->numero_bon} transformée en vente #{$sale->numero}",
            ['commande_id' => $commande->id, 'sale_id' => $sale->id]
        );

        return response()->json([
            'commande' => $commande->fresh()->load(['client:id,nom,telephone', 'items.product:id,nom,unite_mesure']),
            'sale'     => $sale,
        ]);
    }

    /** GET /commandes-clients/{id}/bon-livraison */
    public function bonLivraison(int $id)
    {
        $commande = CommandeClient::with(['client', 'items.product:id,nom,unite_mesure'])->findOrFail($id);
        $org      = app('current_user')->organisation;

        $pdf = Pdf::loadView('commandes.bon_livraison', [
            'commande' => $commande,
            'org'      => $org,
        ])->setPaper('a4');

        return $pdf->download("{$commande->numero_bon}.pdf");
    }

    /** Auto-incrementing per-organisation delivery note number: BL-YYYY-NNNN. */
    private function nextNumeroBon(): string
    {
        $year   = now()->format('Y');
        $prefix = "BL-{$year}-";

        $count = CommandeClient::where('numero_bon', 'like', $prefix . '%')
            ->lockForUpdate()
            ->count();

        return $prefix . str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }
}
