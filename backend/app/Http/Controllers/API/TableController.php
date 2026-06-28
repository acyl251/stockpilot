<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Organisation;
use App\Models\RestaurantTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TableController extends Controller
{
    private function requireRestauration(): ?JsonResponse
    {
        $org = Organisation::findOrFail(app('current_organisation_id'));
        if (! $org->isRestauration()) {
            return response()->json(['message' => 'La gestion des tables est réservée au secteur restauration.'], 403);
        }
        return null;
    }

    public function index(): JsonResponse
    {
        if ($err = $this->requireRestauration()) return $err;

        $tables = RestaurantTable::with(['currentOrder.items'])
            ->where('active', true)
            ->orderByRaw("CAST(numero AS INTEGER) ASC, numero ASC")
            ->get()
            ->map(fn ($t) => $this->format($t));

        return response()->json($tables);
    }

    public function store(Request $request): JsonResponse
    {
        if ($err = $this->requireRestauration()) return $err;

        $validated = $request->validate([
            'numero'   => 'required|string|max:50',
            'capacite' => 'nullable|integer|min:1|max:99',
        ]);

        $table = RestaurantTable::create($validated);

        return response()->json($this->format($table), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if ($err = $this->requireRestauration()) return $err;

        $table     = RestaurantTable::findOrFail($id);
        $validated = $request->validate([
            'numero'   => 'sometimes|required|string|max:50',
            'capacite' => 'nullable|integer|min:1|max:99',
            'statut'   => 'nullable|in:libre,occupee',
            'active'   => 'nullable|boolean',
        ]);

        $table->update($validated);

        return response()->json($this->format($table->fresh('currentOrder')));
    }

    public function destroy(int $id): JsonResponse
    {
        if ($err = $this->requireRestauration()) return $err;

        RestaurantTable::findOrFail($id)->update(['active' => false]);

        return response()->json(['message' => 'Table désactivée.']);
    }

    private function format(RestaurantTable $t): array
    {
        $order = $t->currentOrder;

        return [
            'id'       => $t->id,
            'numero'   => $t->numero,
            'capacite' => $t->capacite,
            'statut'   => $t->statut,
            'active'   => $t->active,
            'current_order' => $order ? [
                'id'         => $order->id,
                'statut'     => $order->statut,
                'created_at' => $order->created_at,
                'total'      => $order->items->sum(fn ($i) => (float) $i->prix_unitaire * $i->quantite),
                'item_count' => $order->items->count(),
            ] : null,
        ];
    }
}
