<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Organisation extends Model
{
    protected $table = 'organisations';

    protected $fillable = [
        'plan_id',
        'nom',
        'slug',
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

    const SECTEUR_COMMERCE     = 'commerce';
    const SECTEUR_RESTAURATION = 'restauration';

    /** La feature recette/fiche technique n'est active qu'en restauration. */
    public function isRestauration(): bool
    {
        return $this->secteur === self::SECTEUR_RESTAURATION;
    }

    /**
     * Generate a unique slug from a name, excluding the given organisation id
     * (so updating the same org keeps its own slug).
     */
    public static function uniqueSlug(string $name, ?int $excludeId = null): string
    {
        $base = Str::slug($name);
        if (! $base) {
            $base = 'restaurant';
        }

        $slug = $base;
        $n    = 2;

        while (
            static::where('slug', $slug)
                ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
                ->exists()
        ) {
            $slug = "{$base}-{$n}";
            $n++;
        }

        return $slug;
    }
}
