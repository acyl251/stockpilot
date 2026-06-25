<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends BaseModel
{
    protected $table = 'clients';

    protected $fillable = [
        'organisation_id',
        'nom',
        'telephone',
        'notes',
    ];

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ClientPayment::class);
    }
}
