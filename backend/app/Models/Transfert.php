<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transfert extends BaseModel
{
    protected $table = 'transferts';

    protected $fillable = [
        'organisation_id',
        'point_source_id',
        'point_dest_id',
        'created_by',
        'note',
    ];

    public function pointSource(): BelongsTo
    {
        return $this->belongsTo(PointDeVente::class, 'point_source_id');
    }

    public function pointDest(): BelongsTo
    {
        return $this->belongsTo(PointDeVente::class, 'point_dest_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransfertItem::class);
    }
}
