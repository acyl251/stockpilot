<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organisation;
use App\Models\RestaurantTable;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Supplement;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function __construct(private OrderService $orderService) {}

    private function requireRestauration(): ?JsonResponse
    {
        $org = Organisation::findOrFail(app('current_organisation_id'));
        if (! $org->isRestauration()) {
            return response()->json(['message' => 'La gestion des commandes est réservée au secteur restauration.'], 403);
        }
        return null;
    }

    public function index(Request $request): JsonResponse
    {
        if ($err = $this->requireRestauration()) return $err;

        $orders = Order::with(['items', 'table'])
            ->when($request->table_id, fn ($q, $id) => $q->where('table_id', $id))
            ->when(
                $request->statut,
                fn ($q, $s) => $q->where('statut', $s),
                fn ($q)      => $q->whereIn('statut', [Order::STATUT_EN_COURS, Order::STATUT_ENVOYEE])
            )
            ->latest()
            ->get()
            ->map(fn ($o) => $this->format($o));

        return response()->json($orders);
    }

    public function show(int $id): JsonResponse
    {
        if ($err = $this->requireRestauration()) return $err;

        $order = Order::with(['items', 'table'])->findOrFail($id);

        return response()->json($this->format($order));
    }

    public function store(Request $request): JsonResponse
    {
        if ($err = $this->requireRestauration()) return $err;

        $validated = $request->validate([
            'table_id'              => 'nullable|integer',
            'type'                  => 'required|in:sur_place,emporter',
            'note'                  => 'nullable|string|max:500',
            'items'                 => 'required|array|min:1',
            'items.*.product_id'    => 'nullable|integer',
            'items.*.supplement_id' => 'nullable|integer',
            'items.*.designation'   => 'required|string|max:200',
            'items.*.quantite'      => 'required|integer|min:1',
            'items.*.prix_unitaire' => 'required|numeric|min:0',
            'items.*.note_ligne'    => 'nullable|string|max:200',
        ]);

        $order = DB::transaction(function () use ($validated) {
            $order = Order::create([
                'table_id'   => $validated['table_id'] ?? null,
                'type'       => $validated['type'],
                'statut'     => Order::STATUT_EN_COURS,
                'note'       => $validated['note'] ?? null,
                'created_by' => app('current_user')->id,
            ]);

            foreach ($validated['items'] as $item) {
                OrderItem::create([
                    'order_id'      => $order->id,
                    'product_id'    => $item['product_id']    ?? null,
                    'supplement_id' => $item['supplement_id'] ?? null,
                    'designation'   => $item['designation'],
                    'quantite'      => $item['quantite'],
                    'prix_unitaire' => $item['prix_unitaire'],
                    'note_ligne'    => $item['note_ligne']    ?? null,
                ]);
            }

            return $order;
        });

        return response()->json($this->format($order->load(['items', 'table'])), 201);
    }

    public function updateItems(Request $request, int $id): JsonResponse
    {
        if ($err = $this->requireRestauration()) return $err;

        $order = Order::findOrFail($id);

        if ($order->statut !== Order::STATUT_EN_COURS) {
            return response()->json(['message' => 'Impossible de modifier une commande déjà envoyée.'], 422);
        }

        $validated = $request->validate([
            'items'                 => 'required|array|min:1',
            'items.*.product_id'    => 'nullable|integer',
            'items.*.supplement_id' => 'nullable|integer',
            'items.*.designation'   => 'required|string|max:200',
            'items.*.quantite'      => 'required|integer|min:1',
            'items.*.prix_unitaire' => 'required|numeric|min:0',
            'items.*.note_ligne'    => 'nullable|string|max:200',
        ]);

        DB::transaction(function () use ($order, $validated) {
            $order->items()->delete();

            foreach ($validated['items'] as $item) {
                OrderItem::create([
                    'order_id'      => $order->id,
                    'product_id'    => $item['product_id']    ?? null,
                    'supplement_id' => $item['supplement_id'] ?? null,
                    'designation'   => $item['designation'],
                    'quantite'      => $item['quantite'],
                    'prix_unitaire' => $item['prix_unitaire'],
                    'note_ligne'    => $item['note_ligne']    ?? null,
                ]);
            }
        });

        return response()->json($this->format($order->fresh(['items', 'table'])));
    }

    public function sendKitchen(int $id): JsonResponse
    {
        if ($err = $this->requireRestauration()) return $err;

        $order = Order::with(['items', 'table'])->findOrFail($id);

        if ($order->statut === Order::STATUT_ENVOYEE) {
            return response()->json(['message' => 'Commande déjà envoyée en cuisine.'], 422);
        }

        if ($order->statut !== Order::STATUT_EN_COURS) {
            return response()->json(['message' => 'Cette commande ne peut pas être envoyée.'], 422);
        }

        DB::transaction(function () use ($order) {
            $this->orderService->decrementOrderStock($order);
            $order->update(['statut' => Order::STATUT_ENVOYEE]);

            if ($order->table_id) {
                $order->table->update(['statut' => RestaurantTable::STATUT_OCCUPEE]);
            }
        });

        return response()->json($this->format($order->fresh(['items', 'table'])));
    }

    /**
     * Pay all active orders for a table:
     *   - aggregate items from all 'envoyee_cuisine' orders into one Sale
     *   - mark those orders as 'payee'
     *   - set the table back to 'libre'
     *
     * NOTE: stock was already decremented at send-kitchen, so we do NOT
     * decrement again here — we only create the accounting Sale record.
     */
    public function pay(Request $request, int $id): JsonResponse
    {
        if ($err = $this->requireRestauration()) return $err;

        $order = Order::findOrFail($id);

        $validated = $request->validate([
            'mode_paiement'  => 'nullable|in:especes,carte,credit',
            'montant_paye'   => 'nullable|numeric|min:0',
            'client_id'      => 'nullable|integer',
            'reference_carte' => 'nullable|string|max:100',
        ]);

        // Collect all 'envoyee_cuisine' orders for this table (or just this order if à emporter)
        if ($order->table_id) {
            $ordersToClose = Order::where('table_id', $order->table_id)
                ->whereIn('statut', [Order::STATUT_EN_COURS, Order::STATUT_ENVOYEE])
                ->with('items')
                ->get();
        } else {
            $ordersToClose = collect([$order->load('items')]);
        }

        if ($ordersToClose->isEmpty()) {
            return response()->json(['message' => 'Aucune commande active à encaisser.'], 422);
        }

        $sale = DB::transaction(function () use ($ordersToClose, $order, $validated) {
            $allItems  = $ordersToClose->flatMap->items;
            $totalTtc  = round($allItems->sum(fn ($i) => (float) $i->prix_unitaire * $i->quantite), 3);
            $modePay   = $validated['mode_paiement'] ?? Sale::MODE_ESPECES;
            $montant   = $validated['montant_paye']  ?? $totalTtc;

            $sale = Sale::create([
                'user_id'         => app('current_user')->id,
                'client_id'       => $validated['client_id'] ?? null,
                'table_id'        => $order->table_id,
                'type_commande'   => $order->table_id ? 'sur_place' : 'emporter',
                'reference_carte' => $modePay === Sale::MODE_CARTE ? ($validated['reference_carte'] ?? null) : null,
                'numero'         => $this->nextSaleNumero(),
                'total_ht'       => $totalTtc,   // taux_tva = 0 en restauration
                'total_tva'      => 0,
                'total_ttc'      => $totalTtc,
                'remise_type'    => null,
                'remise_valeur'  => null,
                'remise_montant' => 0,
                'mode_paiement'  => $modePay,
                'montant_paye'   => $montant,
                'monnaie_rendue' => max(0, round($montant - $totalTtc, 3)),
                'montant_regle'  => $totalTtc,
                'statut'         => Sale::STATUT_PAYEE,
                'date_vente'     => now(),
            ]);

            foreach ($allItems as $item) {
                $productId = $item->product_id;

                // Supplements: product_id was null in order_items — resolve ingredient_id
                if (! $productId && $item->supplement_id) {
                    $supp      = Supplement::find($item->supplement_id);
                    $productId = $supp?->ingredient_id;
                }

                if (! $productId) continue;

                SaleItem::create([
                    'sale_id'             => $sale->id,
                    'product_id'          => $productId,
                    'supplement_id'       => $item->supplement_id,
                    'designation'         => $item->designation,
                    'quantite'            => $item->quantite,
                    'prix_unitaire_ht'    => (float) $item->prix_unitaire,
                    'prix_achat_unitaire' => 0,   // stock already managed at send-kitchen
                    'taux_tva'            => 0,
                    'prix_unitaire_ttc'   => (float) $item->prix_unitaire,
                    'total_ligne_ttc'     => round((float) $item->prix_unitaire * $item->quantite, 3),
                ]);
            }

            // Close all orders
            $ordersToClose->each(fn ($o) => $o->update(['statut' => Order::STATUT_PAYEE]));

            // Free the table
            if ($order->table_id) {
                RestaurantTable::find($order->table_id)
                    ?->update(['statut' => RestaurantTable::STATUT_LIBRE]);
            }

            return $sale;
        });

        return response()->json([
            'message' => 'Table libérée. Vente enregistrée.',
            'sale_id' => $sale->id,
            'total'   => $sale->total_ttc,
        ]);
    }

    /**
     * Check ingredient stock for an order that is still 'en_cours'.
     * Returns { warnings: [...] } — no side effects.
     */
    public function checkIngredients(int $id): JsonResponse
    {
        if ($err = $this->requireRestauration()) return $err;

        $order = Order::with('items')->findOrFail($id);

        $items = $order->items->map(fn ($i) => [
            'product_id'    => $i->product_id,
            'supplement_id' => $i->supplement_id,
            'quantite'      => $i->quantite,
        ])->toArray();

        $warnings = $this->orderService->checkIngredientWarnings($items);

        return response()->json(['warnings' => $warnings]);
    }

    private function nextSaleNumero(): string
    {
        $prefix = 'TKT-' . now()->format('Ymd') . '-';
        $count  = Sale::where('numero', 'like', $prefix . '%')->count();

        return $prefix . str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }

    private function format(Order $order): array
    {
        return [
            'id'         => $order->id,
            'type'       => $order->type,
            'statut'     => $order->statut,
            'note'       => $order->note,
            'created_at' => $order->created_at,
            'total'      => $order->items->sum(fn ($i) => (float) $i->prix_unitaire * $i->quantite),
            'table'      => $order->table
                ? ['id' => $order->table->id, 'numero' => $order->table->numero]
                : null,
            'items' => $order->items->map(fn ($i) => [
                'id'            => $i->id,
                'product_id'    => $i->product_id,
                'supplement_id' => $i->supplement_id,
                'designation'   => $i->designation,
                'quantite'      => $i->quantite,
                'prix_unitaire' => (float) $i->prix_unitaire,
                'note_ligne'    => $i->note_ligne,
            ])->values(),
        ];
    }
}
