<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DemoRequest extends Model
{
    protected $fillable = [
        'prenom', 'nom', 'email', 'telephone',
        'societe', 'secteur', 'plan_souhaite', 'message', 'statut',
    ];
}
