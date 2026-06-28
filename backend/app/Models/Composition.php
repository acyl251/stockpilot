<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Composition extends BaseModel
{
    protected $table = 'compositions';

    protected $fillable = [
        'organisation_id',
        'produit_compose_id',
        'composant_id',
        'quantite',
        'unite',
    ];

    protected $casts = [
        'quantite' => 'decimal:3',
    ];

    /** Le produit composé (ex : le sandwich). */
    public function produitCompose(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'produit_compose_id');
    }

    /** L'ingrédient (ex : le fromage). */
    public function composant(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'composant_id');
    }
}
