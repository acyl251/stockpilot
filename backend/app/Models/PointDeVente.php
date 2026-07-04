<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PointDeVente extends BaseModel
{
    protected $table = 'points_de_vente';

    protected $fillable = [
        'organisation_id',
        'nom',
        'type',
        'actif',
    ];

    protected $casts = [
        'actif' => 'boolean',
    ];

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function stockParPoint(): HasMany
    {
        return $this->hasMany(StockParPoint::class);
    }
}
