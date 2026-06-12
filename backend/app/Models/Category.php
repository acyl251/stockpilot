<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends BaseModel
{
    protected $table = 'categories';

    protected $fillable = [
        'organisation_id',
        'nom',
        'description',
        'couleur',
        'actif',
    ];

    protected $casts = [
        'actif' => 'boolean',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
