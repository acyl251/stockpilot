<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Starter : gratuit, limites réelles
        DB::table('plans')->where('nom', 'Starter')->update([
            'prix_mensuel'     => 0,
            'max_utilisateurs' => 1,
            'max_produits'     => 30,
            'ia_activee'       => false,
            'updated_at'       => now(),
        ]);

        // Pro : 30 DT/mois, 5 users, 500 produits
        DB::table('plans')->where('nom', 'Pro')->update([
            'prix_mensuel'     => 30,
            'max_utilisateurs' => 5,
            'max_produits'     => 500,
            'ia_activee'       => true,
            'updated_at'       => now(),
        ]);

        // Enterprise → Entreprise : 50 DT/mois, illimité (9999)
        DB::table('plans')->where('nom', 'Enterprise')->update([
            'nom'              => 'Entreprise',
            'prix_mensuel'     => 50,
            'max_utilisateurs' => 9999,
            'max_produits'     => 999999,
            'ia_activee'       => true,
            'updated_at'       => now(),
        ]);

        // Essentiel : 20 DT/mois, 2 users, 200 produits
        DB::table('plans')->insert([
            'nom'              => 'Essentiel',
            'prix_mensuel'     => 20,
            'max_utilisateurs' => 2,
            'max_produits'     => 200,
            'ia_activee'       => false,
            'actif'            => true,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('plans')->where('nom', 'Essentiel')->delete();
        DB::table('plans')->where('nom', 'Entreprise')->update([
            'nom'              => 'Enterprise',
            'prix_mensuel'     => 399,
            'max_utilisateurs' => 50,
            'max_produits'     => 99999,
        ]);
        DB::table('plans')->where('nom', 'Pro')->update([
            'prix_mensuel'     => 149,
            'max_utilisateurs' => 10,
            'max_produits'     => 2000,
        ]);
        DB::table('plans')->where('nom', 'Starter')->update([
            'prix_mensuel'     => 49,
            'max_utilisateurs' => 3,
            'max_produits'     => 200,
        ]);
    }
};
