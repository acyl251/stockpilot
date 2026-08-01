<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommandeClient extends BaseModel
{
    protected $table = 'commandes_clients';

    protected $fillable = [
        'organisation_id',
        'client_id',
        'nom_client',
        'telephone_client',
        'adresse_livraison',
        'statut',
        'note',
        'numero_bon',
        'total_ttc',
        'type_paiement',
        'sale_id',
    ];

    protected $casts = [
        'total_ttc' => 'decimal:3',
    ];

    const STATUT_EN_PREPARATION = 'en_preparation';
    const STATUT_PRETE          = 'prete';
    const STATUT_LIVREE         = 'livree';
    const STATUT_PAYEE          = 'payee';
    const STATUT_ANNULEE        = 'annulee';

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CommandeClientItem::class, 'commande_client_id');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
