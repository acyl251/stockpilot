<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends BaseModel
{
    protected $table = 'sale_items';

    protected $fillable = [
        'organisation_id',
        'sale_id',
        'product_id',
        'supplement_id',
        'designation',
        'quantite',
        'prix_unitaire_ht',
        'prix_achat_unitaire',
        'taux_tva',
        'prix_unitaire_ttc',
        'total_ligne_ttc',
    ];

    protected $casts = [
        'quantite'            => 'decimal:3',
        'prix_unitaire_ht'    => 'decimal:3',
        'prix_achat_unitaire' => 'decimal:3',
        'taux_tva'            => 'decimal:2',
        'prix_unitaire_ttc' => 'decimal:3',
        'total_ligne_ttc'   => 'decimal:3',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supplement(): BelongsTo
    {
        return $this->belongsTo(Supplement::class);
    }
}
