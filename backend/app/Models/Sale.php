<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends BaseModel
{
    protected $table = 'sales';

    protected $fillable = [
        'organisation_id',
        'user_id',
        'client_id',
        'table_id',
        'point_de_vente_id',
        'type_commande',
        'reference_carte',
        'numero',
        'numero_facture',
        'total_ht',
        'total_tva',
        'total_ttc',
        'remise_type',
        'remise_valeur',
        'remise_montant',
        'mode_paiement',
        'montant_paye',
        'monnaie_rendue',
        'montant_regle',
        'statut',
        'date_vente',
    ];

    protected $casts = [
        'total_ht'       => 'decimal:3',
        'total_tva'      => 'decimal:3',
        'total_ttc'      => 'decimal:3',
        'remise_valeur'  => 'decimal:3',
        'remise_montant' => 'decimal:3',
        'montant_paye'   => 'decimal:3',
        'monnaie_rendue' => 'decimal:3',
        'montant_regle'  => 'decimal:3',
        'date_vente'     => 'datetime',
    ];

    protected $appends = ['reste_a_payer', 'statut_paiement'];

    const MODE_ESPECES = 'especes';
    const MODE_CARTE   = 'carte';
    const MODE_CREDIT  = 'credit';

    const STATUT_PAYEE   = 'payee';
    const STATUT_ANNULEE = 'annulee';

    public function isCancelled(): bool
    {
        return $this->statut === self::STATUT_ANNULEE;
    }

    /** Reste dû sur cette vente (0 si annulée ou soldée). */
    public function getResteAPayerAttribute(): float
    {
        if ($this->isCancelled()) {
            return 0.0;
        }
        return round(max(0, (float) $this->total_ttc - (float) $this->montant_regle), 3);
    }

    public function getStatutPaiementAttribute(): string
    {
        if ($this->isCancelled())              return 'annulee';
        if ($this->reste_a_payer <= 0)         return 'paye';
        if ((float) $this->montant_regle > 0)  return 'partiel';
        return 'impaye';
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function restaurantTable(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'table_id');
    }
}
