<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TypeAttribute extends BaseModel
{
    protected $table = 'type_attributes';

    protected $fillable = [
        'organisation_id',
        'product_type_id',
        'nom',
        'label',
        'type_donnee',
        'obligatoire',
        'valeur_defaut',
        'options_select',
        'ordre',
    ];

    protected $casts = [
        'obligatoire'    => 'boolean',
        'options_select' => 'array',
        'ordre'          => 'integer',
    ];

    const TYPE_DONNEE_TEXT    = 'text';
    const TYPE_DONNEE_NUMBER  = 'number';
    const TYPE_DONNEE_DATE    = 'date';
    const TYPE_DONNEE_BOOLEAN = 'boolean';
    const TYPE_DONNEE_SELECT  = 'select';

    const TYPES_DONNEE = [
        self::TYPE_DONNEE_TEXT,
        self::TYPE_DONNEE_NUMBER,
        self::TYPE_DONNEE_DATE,
        self::TYPE_DONNEE_BOOLEAN,
        self::TYPE_DONNEE_SELECT,
    ];

    public function productType(): BelongsTo
    {
        return $this->belongsTo(ProductType::class);
    }
}
