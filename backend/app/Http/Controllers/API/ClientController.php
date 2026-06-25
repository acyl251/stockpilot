<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Sale;
use App\Services\ClientService;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClientController extends Controller
{
    public function __construct(private ClientService $clientService) {}

    public function index(Request $request): JsonResponse
    {
        $orgId = app('current_organisation_id');

        // Solde dû calculé par sous-requête (ventes non annulées).
        $soldeExpr = "(SELECT COALESCE(SUM(s.total_ttc - s.montant_regle), 0)
                       FROM sales s
                       WHERE s.client_id = clients.id
                         AND s.organisation_id = clients.organisation_id
                         AND s.statut != 'annulee')";

        $clients = Client::query()
            ->select('clients.*')
            ->selectRaw("$soldeExpr as solde")
            ->when($request->search, fn($q, $s) => $q->where(fn($w) =>
                $w->where('nom', 'like', "%$s%")->orWhere('telephone', 'like', "%$s%")
            ))
            ->when($request->boolean('debiteurs'), fn($q) => $q->whereRaw("$soldeExpr > 0"))
            ->orderBy('nom')
            ->get();

        return response()->json($clients);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nom'       => 'required|string|max:150',
            'telephone' => 'nullable|string|max:30',
            'notes'     => 'nullable|string|max:500',
        ]);

        $client = Client::create($data);

        return response()->json($client, 201);
    }

    public function show(int $id): JsonResponse
    {
        $client = Client::findOrFail($id);

        $sales = Sale::with('user:id,nom,prenom')
            ->where('client_id', $id)
            ->latest('date_vente')
            ->get();

        $payments = $client->payments()
            ->with('user:id,nom,prenom')
            ->latest('date_paiement')
            ->get();

        return response()->json([
            'client'   => $client,
            'solde'    => $this->clientService->solde($id),
            'sales'    => $sales,
            'payments' => $payments,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $client = Client::findOrFail($id);

        $data = $request->validate([
            'nom'       => 'sometimes|required|string|max:150',
            'telephone' => 'nullable|string|max:30',
            'notes'     => 'nullable|string|max:500',
        ]);

        $client->update($data);

        return response()->json($client);
    }

    /** Encaisser un paiement d'un client (imputé aux plus anciennes ventes). */
    public function pay(Request $request, int $id): JsonResponse
    {
        Client::findOrFail($id); // garde le scope tenant

        $data = $request->validate([
            'montant'       => 'required|numeric|min:0.001',
            'mode_paiement' => 'nullable|in:especes,carte',
            'note'          => 'nullable|string|max:255',
        ]);

        $payment = $this->clientService->recordPayment(
            clientId:     $id,
            montant:      (float) $data['montant'],
            userId:       app('current_user')->id,
            modePaiement: $data['mode_paiement'] ?? 'especes',
            note:         $data['note'] ?? null,
        );

        return response()->json([
            'payment' => $payment,
            'solde'   => $this->clientService->solde($id),
        ], 201);
    }

    /** Relancer un client par WhatsApp pour son solde impayé. */
    public function remind(int $id, WhatsAppService $wa): JsonResponse
    {
        $client = Client::findOrFail($id);

        if (! $client->telephone) {
            return $this->errorResponse('Ce client n\'a pas de numéro de téléphone.', 422);
        }

        $solde = $this->clientService->solde($id);
        if ($solde <= 0) {
            return $this->errorResponse('Ce client n\'a aucun montant dû.', 422);
        }

        $org     = app('current_user')->organisation;
        $message = str_replace(
            [':client', ':solde', ':org'],
            [$client->nom, number_format($solde, 3, ',', ' ') . ' TND', $org->nom],
            config('whatsapp.templates.reminder'),
        );

        $result = $wa->send($client->telephone, $message);

        return response()->json($result + ['message_text' => $message]);
    }
}
