<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Fournisseur extends BaseModel
{
    protected $table = 'fournisseurs';

    protected $fillable = [
        'organisation_id',
        'nom',
        'telephone',
        'email',
        'adresse',
        'note',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function commandes(): HasMany
    {
        return $this->hasMany(CommandeFournisseur::class, 'fournisseur_id');
    }
}
