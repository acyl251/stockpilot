<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Fournisseur;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim($request->string('q'));

        if (mb_strlen($q) < 2) {
            return response()->json([
                'produits'     => [],
                'clients'      => [],
                'ventes'       => [],
                'fournisseurs' => [],
            ]);
        }

        $limit = min((int) ($request->limit ?? 5), 10);
        $like  = '%' . $q . '%';

        $produits = Product::where('actif', true)
            ->where(fn($w) => $w
                ->where('nom',       'LIKE', $like)
                ->orWhere('reference', 'LIKE', $like)
            )
            ->select('id', 'nom', 'reference', 'type', 'prix_vente_ht', 'taux_tva')
            ->limit($limit)
            ->get()
            ->map(fn($p) => [
                'id'          => $p->id,
                'nom'         => $p->nom,
                'reference'   => $p->reference,
                'type'        => $p->type,
                'prix_vente'  => $p->prix_vente_ttc,
                'link'        => '/app/products/' . $p->id,
            ]);

        $clients = Client::where(fn($w) => $w
                ->where('nom',       'LIKE', $like)
                ->orWhere('telephone', 'LIKE', $like)
            )
            ->select('id', 'nom', 'telephone')
            ->limit($limit)
            ->get()
            ->map(fn($c) => [
                'id'        => $c->id,
                'nom'       => $c->nom,
                'telephone' => $c->telephone,
                'link'      => '/app/clients',
            ]);

        $ventes = Sale::where(fn($w) => $w
                ->where('numero',         'LIKE', $like)
                ->orWhere('numero_facture', 'LIKE', $like)
            )
            ->select('id', 'numero', 'numero_facture', 'total_ttc', 'date_vente')
            ->latest('date_vente')
            ->limit($limit)
            ->get()
            ->map(fn($s) => [
                'id'       => $s->id,
                'numero'   => $s->numero ?? $s->numero_facture,
                'total'    => $s->total_ttc,
                'date'     => $s->date_vente,
                'link'     => '/app/ventes',
            ]);

        $fournisseurs = Fournisseur::where('nom', 'LIKE', $like)
            ->select('id', 'nom', 'telephone')
            ->limit($limit)
            ->get()
            ->map(fn($f) => [
                'id'        => $f->id,
                'nom'       => $f->nom,
                'telephone' => $f->telephone,
                'link'      => '/app/fournisseurs',
            ]);

        return response()->json([
            'produits'     => $produits,
            'clients'      => $clients,
            'ventes'       => $ventes,
            'fournisseurs' => $fournisseurs,
        ]);
    }
}
