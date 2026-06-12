<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 100);
            $table->unsignedInteger('max_utilisateurs')->default(5);
            $table->unsignedInteger('max_produits')->default(100);
            $table->boolean('ia_activee')->default(false);
            $table->decimal('prix_mensuel', 10, 3)->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        DB::table('plans')->insert([
            ['nom' => 'Starter',    'max_utilisateurs' => 3,  'max_produits' => 200,   'ia_activee' => false, 'prix_mensuel' => 49.000,  'created_at' => now(), 'updated_at' => now()],
            ['nom' => 'Pro',        'max_utilisateurs' => 10, 'max_produits' => 2000,  'ia_activee' => true,  'prix_mensuel' => 149.000, 'created_at' => now(), 'updated_at' => now()],
            ['nom' => 'Enterprise', 'max_utilisateurs' => 50, 'max_produits' => 99999, 'ia_activee' => true,  'prix_mensuel' => 399.000, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
