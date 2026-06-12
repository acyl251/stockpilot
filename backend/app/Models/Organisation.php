<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organisation extends Model
{
    protected $table = 'organisations';

    protected $fillable = [
        'plan_id',
        'nom',
        'secteur',
        'email_contact',
        'telephone',
        'adresse',
        'matricule_fiscal',
        'onboarding_complete',
        'ia_catalog_seeded_count',
        'ia_catalog_seeded_at',
        'actif',
    ];

    protected $casts = [
        'onboarding_complete'     => 'boolean',
        'actif'                   => 'boolean',
        'ia_catalog_seeded_count' => 'integer',
        'ia_catalog_seeded_at'    => 'datetime',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function productTypes(): HasMany
    {
        return $this->hasMany(ProductType::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function hasAIEnabled(): bool
    {
        return $this->plan?->ia_activee ?? false;
    }
}
