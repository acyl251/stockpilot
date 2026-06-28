<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RestaurantTable extends BaseModel
{
    protected $table    = 'tables_restaurant';
    protected $fillable = ['organisation_id', 'numero', 'capacite', 'statut', 'active'];
    protected $casts    = ['capacite' => 'integer', 'active' => 'boolean'];

    const STATUT_LIBRE   = 'libre';
    const STATUT_OCCUPEE = 'occupee';

    public function currentOrder(): HasOne
    {
        return $this->hasOne(Order::class, 'table_id')
            ->whereIn('statut', [Order::STATUT_EN_COURS, Order::STATUT_ENVOYEE])
            ->latest();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'table_id');
    }
}
