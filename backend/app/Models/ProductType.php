<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductType extends BaseModel
{
    protected $table = 'product_types';

    protected $fillable = [
        'organisation_id',
        'nom',
        'icone',
        'description',
        'suggere_par_ia',
        'actif',
    ];

    protected $casts = [
        'suggere_par_ia' => 'boolean',
        'actif'          => 'boolean',
    ];

    public function attributes(): HasMany
    {
        return $this->hasMany(TypeAttribute::class)->orderBy('ordre');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
