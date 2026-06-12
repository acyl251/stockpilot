<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $table = 'plans';

    protected $fillable = [
        'nom',
        'max_utilisateurs',
        'max_produits',
        'ia_activee',
        'prix_mensuel',
        'actif',
    ];

    protected $casts = [
        'ia_activee' => 'boolean',
        'actif'      => 'boolean',
        'prix_mensuel' => 'decimal:3',
    ];

    public function organisations(): HasMany
    {
        return $this->hasMany(Organisation::class);
    }
}
