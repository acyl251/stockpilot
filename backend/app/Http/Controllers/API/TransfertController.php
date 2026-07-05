<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Transfert;
use App\Services\TransfertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransfertController extends Controller
{
    public function __construct(private TransfertService $transfertService) {}

    private function guardAdmin(): ?JsonResponse
    {
        $role = app('current_user')->role;
        if (! in_array($role, ['admin', 'super_admin'])) {
            return response()->json([
                'message' => 'Accès réservé aux administrateurs.',
            ], 403);
        }
        return null;
    }

    public function index(Request $request): JsonResponse
    {
        if ($err = $this->guardAdmin()) return $err;

        $transferts = Transfert::with([
                'pointSource:id,nom',
                'pointDest:id,nom',
                'createdBy:id,nom,prenom',
            ])
            ->withCount('items')
            ->when($request->date_debut, fn($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->date_fin,   fn($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->when($request->point_id,   fn($q, $pid) => $q->where(fn($w) => $w
                ->where('point_source_id', $pid)
                ->orWhere('point_dest_id', $pid)
            ))
            ->latest()
            ->paginate($request->per_page ?? 25);

        return response()->json($transferts);
    }

    public function show(int $id): JsonResponse
    {
        if ($err = $this->guardAdmin()) return $err;

        $transfert = Transfert::with([
            'pointSource:id,nom,type',
            'pointDest:id,nom,type',
            'createdBy:id,nom,prenom',
            'items.product:id,nom,reference,unite_mesure',
        ])->findOrFail($id);

        return response()->json($transfert);
    }

    public function store(Request $request): JsonResponse
    {
        if ($err = $this->guardAdmin()) return $err;

        $data = $request->validate([
            'point_source_id'      => 'required|integer|exists:points_de_vente,id',
            'point_dest_id'        => 'required|integer|exists:points_de_vente,id',
            'items'                => 'required|array|min:1',
            'items.*.product_id'   => 'required|integer|exists:products,id',
            'items.*.quantite'     => 'required|numeric|min:0.001',
            'note'                 => 'nullable|string|max:500',
        ]);

        $transfert = $this->transfertService->execute(
            sourceId: (int) $data['point_source_id'],
            destId:   (int) $data['point_dest_id'],
            items:    $data['items'],
            userId:   app('current_user')->id,
            note:     $data['note'] ?? null,
        );

        return response()->json($transfert, 201);
    }
}
