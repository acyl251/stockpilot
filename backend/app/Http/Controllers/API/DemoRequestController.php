<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\DemoRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DemoRequestController extends Controller
{
    /** POST /demo-request — public, no auth */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'prenom'        => 'required|string|max:100',
            'nom'           => 'required|string|max:100',
            'email'         => 'required|email|max:150',
            'telephone'     => 'nullable|string|max:30',
            'societe'       => 'required|string|max:150',
            'secteur'       => 'nullable|string|max:100',
            'plan_souhaite' => 'nullable|in:starter,pro,enterprise',
            'message'       => 'nullable|string|max:1000',
        ]);

        $demo = DemoRequest::create($data);

        return response()->json([
            'message' => 'Votre demande a bien été envoyée. Nous vous contacterons sous 24h.',
            'id'      => $demo->id,
        ], 201);
    }

    /** GET /super-admin/demo-requests — super admin only */
    public function index(): JsonResponse
    {
        $requests = DemoRequest::orderByRaw("CASE statut WHEN 'en_attente' THEN 0 WHEN 'traite' THEN 1 ELSE 2 END")
            ->orderByDesc('created_at')
            ->get();

        return response()->json($requests);
    }

    /** PATCH /super-admin/demo-requests/{id} — update status */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'statut' => 'required|in:en_attente,traite,rejete',
        ]);

        $demo = DemoRequest::findOrFail($id);
        $demo->update($data);

        return response()->json($demo);
    }
}
