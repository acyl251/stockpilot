<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    protected $table = 'users';

    protected $fillable = [
        'organisation_id',
        'nom',
        'prenom',
        'email',
        'password',
        'role',
        'actif',
        'tentatives_connexion',
        'verrouille_jusqu_a',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'actif'               => 'boolean',
        'verrouille_jusqu_a'  => 'datetime',
        'tentatives_connexion' => 'integer',
    ];

    // JWT interface
    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'organisation_id' => $this->organisation_id,
            'role'            => $this->role,
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isLocked(): bool
    {
        return $this->verrouille_jusqu_a && $this->verrouille_jusqu_a->isFuture();
    }

    public function incrementLoginAttempts(): void
    {
        $this->tentatives_connexion++;

        if ($this->tentatives_connexion >= 5) {
            $this->verrouille_jusqu_a = now()->addMinutes(15);
        }

        $this->save();
    }

    public function resetLoginAttempts(): void
    {
        $this->tentatives_connexion = 0;
        $this->verrouille_jusqu_a   = null;
        $this->save();
    }
}
