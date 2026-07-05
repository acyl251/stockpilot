<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DemoRequest extends Model
{
    protected $fillable = [
        'prenom', 'nom', 'email', 'telephone',
        'societe', 'secteur', 'plan_souhaite', 'message',
        'statut', 'email_token', 'email_verified_at',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function isEmailVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function isTokenExpired(): bool
    {
        return $this->email_verified_at === null
            && $this->created_at !== null
            && $this->created_at->diffInHours(now()) > 48;
    }
}
