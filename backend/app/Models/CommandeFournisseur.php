<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommandeFournisseur extends BaseModel
{
    protected $table = 'commandes_fournisseur';

    protected $fillable = [
        'organisation_id',
        'fournisseur_id',
        'statut',
        'date_commande',
        'date_livraison_prevue',
        'note',
    ];

    protected $casts = [
        'date_commande'         => 'date',
        'date_livraison_prevue' => 'date',
    ];

    const STATUT_BROUILLON = 'brouillon';
    const STATUT_ENVOYEE   = 'envoyee';
    const STATUT_RECUE     = 'recue';
    const STATUT_ANNULEE   = 'annulee';

    public function fournisseur(): BelongsTo
    {
        return $this->belongsTo(Fournisseur::class, 'fournisseur_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CommandeFournisseurItem::class, 'commande_id');
    }
}
