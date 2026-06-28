<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Supplement extends BaseModel
{
    protected $table = 'supplements';

    protected $fillable = [
        'organisation_id',
        'nom',
        'prix_vente',
        'ingredient_id',
        'quantite',
        'unite',
        'active',
    ];

    protected $casts = [
        'prix_vente' => 'float',
        'quantite'   => 'float',
        'active'     => 'boolean',
    ];

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'ingredient_id');
    }
}
