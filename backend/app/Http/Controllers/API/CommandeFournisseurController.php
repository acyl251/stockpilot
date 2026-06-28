<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CommandeFournisseur;
use App\Models\CommandeFournisseurItem;
use App\Models\Product;
use App\Services\ActivityLogService;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommandeFournisseurController extends Controller
{
    public function __construct(private StockService $stockService) {}

    public function index(Request $request): JsonResponse
    {
        $query = CommandeFournisseur::with(['fournisseur:id,nom', 'items'])
            ->orderByDesc('date_commande');

        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }
        if ($request->filled('fournisseur_id')) {
            $query->where('fournisseur_id', $request->fournisseur_id);
        }

        return response()->json($query->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fournisseur_id'        => 'required|integer',
            'date_commande'         => 'required|date',
            'date_livraison_prevue' => 'nullable|date',
            'note'                  => 'nullable|string',
            'statut'                => 'nullable|in:brouillon,envoyee',
            'items'                 => 'required|array|min:1',
            'items.*.product_id'    => 'required|integer|exists:products,id,actif,1',
            'items.*.quantite'      => 'required|numeric|min:0.001',
            'items.*.prix_unitaire' => 'nullable|numeric|min:0',
            'items.*.unite'         => 'required|string|max:50',
        ]);

        $commande = DB::transaction(function () use ($data) {
            $commande = CommandeFournisseur::create([
                'fournisseur_id'        => $data['fournisseur_id'],
                'date_commande'         => $data['date_commande'],
                'date_livraison_prevue' => $data['date_livraison_prevue'] ?? null,
                'note'                  => $data['note'] ?? null,
                'statut'                => $data['statut'] ?? CommandeFournisseur::STATUT_BROUILLON,
            ]);

            foreach ($data['items'] as $item) {
                CommandeFournisseurItem::create([
                    'commande_id'   => $commande->id,
                    'product_id'    => $item['product_id'],
                    'quantite'      => $item['quantite'],
                    'prix_unitaire' => $item['prix_unitaire'] ?? null,
                    'unite'         => $item['unite'],
                ]);
            }

            return $commande;
        });

        return response()->json(
            $commande->load(['fournisseur:id,nom', 'items.product:id,nom,unite_mesure']),
            201
        );
    }

    public function show(int $id): JsonResponse
    {
        $commande = CommandeFournisseur::with([
            'fournisseur',
            'items.product:id,nom,reference,unite_mesure',
        ])->findOrFail($id);

        return response()->json($commande);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $commande = CommandeFournisseur::findOrFail($id);

        if ($commande->statut === CommandeFournisseur::STATUT_RECUE) {
            return response()->json(['message' => 'Une commande reçue ne peut plus être modifiée.'], 422);
        }

        $data = $request->validate([
            'fournisseur_id'        => 'sometimes|integer',
            'date_commande'         => 'sometimes|date',
            'date_livraison_prevue' => 'nullable|date',
            'note'                  => 'nullable|string',
            'statut'                => 'nullable|in:brouillon,envoyee,annulee',
            'items'                 => 'sometimes|array|min:1',
            'items.*.id'            => 'nullable|integer',
            'items.*.product_id'    => 'required_with:items|integer|exists:products,id,actif,1',
            'items.*.quantite'      => 'required_with:items|numeric|min:0.001',
            'items.*.prix_unitaire' => 'nullable|numeric|min:0',
            'items.*.unite'         => 'required_with:items|string|max:50',
        ]);

        DB::transaction(function () use ($commande, $data) {
            $commande->update(collect($data)->except('items')->toArray());

            if (isset($data['items'])) {
                $commande->items()->delete();
                foreach ($data['items'] as $item) {
                    CommandeFournisseurItem::create([
                        'commande_id'   => $commande->id,
                        'product_id'    => $item['product_id'],
                        'quantite'      => $item['quantite'],
                        'prix_unitaire' => $item['prix_unitaire'] ?? null,
                        'unite'         => $item['unite'],
                    ]);
                }
            }
        });

        return response()->json(
            $commande->fresh()->load(['fournisseur:id,nom', 'items.product:id,nom,unite_mesure'])
        );
    }

    public function envoyer(int $id): JsonResponse
    {
        $commande = CommandeFournisseur::findOrFail($id);

        if ($commande->statut !== CommandeFournisseur::STATUT_BROUILLON) {
            return response()->json(['message' => 'Seul un brouillon peut être marqué comme envoyé.'], 422);
        }

        $commande->update(['statut' => CommandeFournisseur::STATUT_ENVOYEE]);
        $commande->load('fournisseur:id,nom');

        ActivityLogService::log('updated', 'fournisseur',
            "Commande envoyée à {$commande->fournisseur->nom}"
        );

        return response()->json($commande->fresh()->load('fournisseur:id,nom'));
    }

    public function receptionner(Request $request, int $id): JsonResponse
    {
        $commande = CommandeFournisseur::with('items.product')->findOrFail($id);

        if ($commande->statut !== CommandeFournisseur::STATUT_ENVOYEE) {
            return response()->json(['message' => 'Seule une commande envoyée peut être réceptionnée.'], 422);
        }

        $data = $request->validate([
            'items'                        => 'required|array|min:1',
            'items.*.item_id'              => 'required|integer',
            'items.*.quantite_recue'       => 'required|numeric|min:0',
            'items.*.prix_unitaire_reel'   => 'nullable|numeric|min:0',
        ]);

        $userId = app('current_user')->id;

        DB::transaction(function () use ($commande, $data, $userId) {
            $itemsById = $commande->items->keyBy('id');

            foreach ($data['items'] as $row) {
                $item = $itemsById->get($row['item_id']);
                if (! $item) {
                    continue;
                }

                $qteRecue = (float) $row['quantite_recue'];
                if ($qteRecue <= 0) {
                    continue;
                }

                // Create stock entry movement
                $this->stockService->createMovement(
                    productId:    $item->product_id,
                    userId:       $userId,
                    type:         'entree',
                    quantite:     $qteRecue,
                    note:         "Réception commande fournisseur #{$commande->id}",
                    enforceStock: false,
                );

                // Update purchase price if provided
                if (! empty($row['prix_unitaire_reel'])) {
                    $item->product->updateQuietly([
                        'prix_achat_ht' => (float) $row['prix_unitaire_reel'],
                    ]);
                }
            }

            $commande->update(['statut' => CommandeFournisseur::STATUT_RECUE]);
        });

        $commande->load('fournisseur:id,nom');
        ActivityLogService::log('received', 'fournisseur',
            "Commande reçue de {$commande->fournisseur->nom} — stock mis à jour",
            ['commande_id' => $commande->id]
        );

        return response()->json($commande->fresh()->load([
            'fournisseur:id,nom',
            'items.product:id,nom,unite_mesure,quantite',
        ]));
    }

    public function destroy(int $id): JsonResponse
    {
        $commande = CommandeFournisseur::findOrFail($id);

        if ($commande->statut === CommandeFournisseur::STATUT_RECUE) {
            return response()->json(['message' => 'Une commande reçue ne peut pas être supprimée.'], 422);
        }

        $commande->delete();
        return response()->json(null, 204);
    }
}
