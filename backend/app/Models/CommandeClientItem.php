<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommandeClientItem extends Model
{
    protected $table = 'commande_client_items';

    protected $fillable = [
        'commande_client_id',
        'product_id',
        'quantite',
        'prix_unitaire',
        'type_prix',
        'total',
    ];

    protected $casts = [
        'quantite'      => 'decimal:3',
        'prix_unitaire' => 'decimal:3',
        'total'         => 'decimal:3',
    ];

    public function commande(): BelongsTo
    {
        return $this->belongsTo(CommandeClient::class, 'commande_client_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
