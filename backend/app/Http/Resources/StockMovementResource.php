<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'product_id'     => $this->product_id,
            'type_mouvement' => $this->type_mouvement,
            'quantite'       => (float) $this->quantite,
            'quantite_avant' => (float) $this->quantite_avant,
            'quantite_apres' => (float) $this->quantite_apres,
            'note'           => $this->note,
            'date_mouvement' => $this->date_mouvement?->toISOString(),
            'product'        => $this->whenLoaded('product', fn() => [
                'id'          => $this->product->id,
                'nom'         => $this->product->nom,
                'reference'   => $this->product->reference,
                'unite_mesure' => $this->product->unite_mesure,
            ]),
            'user'           => $this->whenLoaded('user', fn() => [
                'id'     => $this->user->id,
                'nom'    => $this->user->nom,
                'prenom' => $this->user->prenom,
            ]),
            'created_at'     => $this->created_at?->toISOString(),
        ];
    }
}
