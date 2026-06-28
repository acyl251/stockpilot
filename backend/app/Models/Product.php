<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends BaseModel
{
    protected $table = 'products';

    protected $fillable = [
        'organisation_id',
        'category_id',
        'product_type_id',
        'nom',
        'reference',
        'description',
        'quantite',
        'seuil_alerte',
        'unite_mesure',
        'prix_achat_ht',
        'taux_tva',
        'prix_vente_ht',
        'type',
        'attributs',
        'actif',
    ];

    const TYPE_SIMPLE  = 'simple';
    const TYPE_COMPOSE = 'compose';

    protected $casts = [
        'quantite'       => 'decimal:3',
        'seuil_alerte'   => 'decimal:3',
        'prix_achat_ht'  => 'decimal:3',
        'taux_tva'       => 'decimal:2',
        'prix_vente_ht'  => 'decimal:3',
        'attributs'      => 'array',
        'actif'          => 'boolean',
    ];

    // On Oracle: virtual DB columns. On SQLite: computed here.
    protected $appends = ['prix_achat_ttc', 'prix_vente_ttc', 'en_alerte', 'en_rupture', 'statut'];

    public function getPrixAchatTtcAttribute(): float
    {
        return round((float) $this->prix_achat_ht * (1 + (float) $this->taux_tva / 100), 3);
    }

    public function getPrixVenteTtcAttribute(): float
    {
        return round((float) $this->prix_vente_ht * (1 + (float) $this->taux_tva / 100), 3);
    }

    public function getEnAlerteAttribute(): bool
    {
        if ($this->isCompose()) return false;
        return $this->quantite > 0 && $this->quantite <= $this->seuil_alerte;
    }

    public function getEnRuptureAttribute(): bool
    {
        if ($this->isCompose()) return false;
        return $this->quantite <= 0;
    }

    public function getStatutAttribute(): string
    {
        if ($this->isCompose()) return 'Composé';
        if ($this->en_rupture) return 'Rupture';
        if ($this->en_alerte)  return 'Alerte';
        return 'En stock';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function productType(): BelongsTo
    {
        return $this->belongsTo(ProductType::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /** Lignes de recette (ce produit composé = somme de ses ingrédients). */
    public function composition(): HasMany
    {
        return $this->hasMany(Composition::class, 'produit_compose_id');
    }

    public function isCompose(): bool
    {
        return $this->type === self::TYPE_COMPOSE;
    }
}
