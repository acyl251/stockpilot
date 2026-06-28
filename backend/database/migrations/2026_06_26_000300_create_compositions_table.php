<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fiche technique / recette : un produit composé (ex. sandwich) est constitué
 * de plusieurs produits ingrédients (ex. fromage, pain).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compositions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained('organisations');
            $table->foreignId('produit_compose_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('composant_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('quantite', 12, 3)->default(0);
            $table->string('unite', 30)->default('unité');
            $table->timestamps();

            $table->index('organisation_id', 'idx_compo_org');
            $table->index('produit_compose_id', 'idx_compo_parent');
            $table->unique(['produit_compose_id', 'composant_id'], 'uq_compo_pair');
        });

        if (DB::connection()->getDriverName() === 'oracle') {
            DB::statement("
                CREATE OR REPLACE TRIGGER trg_compositions_updated_at
                BEFORE UPDATE ON compositions
                FOR EACH ROW
                BEGIN
                    :NEW.updated_at := SYSTIMESTAMP;
                END;
            ");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'oracle') {
            DB::statement('DROP TRIGGER trg_compositions_updated_at');
        }
        Schema::dropIfExists('compositions');
    }
};
