<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Normalise les valeurs de secteur invalides ('Commerce de détail', NULL, etc.)
 * vers 'restauration' — valeur par défaut pour ce projet.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('organisations')
            ->where(function ($q) {
                $q->whereNotIn('secteur', ['commerce', 'restauration'])
                  ->orWhereNull('secteur');
            })
            ->update(['secteur' => 'restauration']);
    }

    public function down(): void
    {
        // Irréversible : les anciennes valeurs (ex : 'Commerce de détail') étaient invalides.
    }
};
