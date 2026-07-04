<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransfertItem extends Model
{
    protected $table = 'transfert_items';

    protected $fillable = [
        'transfert_id',
        'product_id',
        'quantite',
        'unite',
    ];

    protected $casts = [
        'quantite' => 'float',
    ];

    public function transfert(): BelongsTo
    {
        return $this->belongsTo(Transfert::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
