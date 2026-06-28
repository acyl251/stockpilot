<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends BaseModel
{
    protected $table    = 'order_items';
    protected $fillable = [
        'organisation_id', 'order_id', 'product_id', 'supplement_id',
        'designation', 'quantite', 'prix_unitaire', 'note_ligne',
    ];
    protected $casts = [
        'quantite'      => 'integer',
        'prix_unitaire' => 'decimal:3',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
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
