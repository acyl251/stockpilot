<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends BaseModel
{
    protected $table    = 'orders';
    protected $fillable = ['organisation_id', 'table_id', 'type', 'statut', 'note', 'created_by'];

    const STATUT_EN_COURS = 'en_cours';
    const STATUT_ENVOYEE  = 'envoyee_cuisine';
    const STATUT_PAYEE    = 'payee';
    const STATUT_ANNULEE  = 'annulee';

    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'table_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getTotal(): float
    {
        return $this->items->sum(fn ($i) => (float) $i->prix_unitaire * $i->quantite);
    }
}
