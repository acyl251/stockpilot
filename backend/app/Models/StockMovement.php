<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends BaseModel
{
    protected $table = 'stock_movements';

    protected $fillable = [
        'organisation_id',
        'product_id',
        'user_id',
        'type_mouvement',
        'quantite',
        'quantite_avant',
        'quantite_apres',
        'note',
        'date_mouvement',
    ];

    protected $casts = [
        'quantite'       => 'decimal:3',
        'quantite_avant' => 'decimal:3',
        'quantite_apres' => 'decimal:3',
        'date_mouvement' => 'datetime',
    ];

    const TYPE_ENTREE  = 'entree';
    const TYPE_SORTIE  = 'sortie';
    const TYPE_AJUST   = 'ajustement';

    const TYPES = [
        self::TYPE_ENTREE,
        self::TYPE_SORTIE,
        self::TYPE_AJUST,
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
