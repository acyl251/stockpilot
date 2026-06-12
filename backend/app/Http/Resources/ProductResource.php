<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'nom'            => $this->nom,
            'reference'      => $this->reference,
            'description'    => $this->description,
            'quantite'       => (float) $this->quantite,
            'seuil_alerte'   => (float) $this->seuil_alerte,
            'unite_mesure'   => $this->unite_mesure,
            'prix_achat_ht'  => (float) $this->prix_achat_ht,
            'taux_tva'       => (float) $this->taux_tva,
            'prix_achat_ttc' => (float) $this->prix_achat_ttc,
            'prix_vente_ht'  => (float) $this->prix_vente_ht,
            'prix_vente_ttc' => (float) $this->prix_vente_ttc,
            'attributs'      => $this->attributs ?? [],
            'statut'         => $this->statut,
            'en_alerte'      => $this->en_alerte,
            'en_rupture'     => $this->en_rupture,
            'actif'          => (bool) $this->actif,
            'category'       => $this->whenLoaded('category', fn() => [
                'id'      => $this->category->id,
                'nom'     => $this->category->nom,
                'couleur' => $this->category->couleur,
            ]),
            'product_type'   => $this->whenLoaded('productType', fn() => [
                'id'  => $this->productType->id,
                'nom' => $this->productType->nom,
            ]),
            'created_at'     => $this->created_at?->toISOString(),
            'updated_at'     => $this->updated_at?->toISOString(),
        ];
    }
}
