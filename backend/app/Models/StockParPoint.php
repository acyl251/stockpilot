<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockParPoint extends BaseModel
{
    protected $table = 'stock_par_point';

    protected $fillable = [
        'organisation_id',
        'product_id',
        'point_de_vente_id',
        'quantite',
    ];

    protected $casts = [
        'quantite' => 'float',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function pointDeVente(): BelongsTo
    {
        return $this->belongsTo(PointDeVente::class);
    }
}
