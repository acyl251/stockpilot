<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommandeFournisseurItem extends Model
{
    protected $table = 'commandes_fournisseur_items';

    protected $fillable = [
        'commande_id',
        'product_id',
        'quantite',
        'prix_unitaire',
        'unite',
    ];

    protected $casts = [
        'quantite'      => 'decimal:3',
        'prix_unitaire' => 'decimal:3',
    ];

    public function commande(): BelongsTo
    {
        return $this->belongsTo(CommandeFournisseur::class, 'commande_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
