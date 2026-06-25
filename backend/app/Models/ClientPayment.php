<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientPayment extends BaseModel
{
    protected $table = 'client_payments';

    protected $fillable = [
        'organisation_id',
        'client_id',
        'user_id',
        'montant',
        'mode_paiement',
        'note',
        'date_paiement',
    ];

    protected $casts = [
        'montant'       => 'decimal:3',
        'date_paiement' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
