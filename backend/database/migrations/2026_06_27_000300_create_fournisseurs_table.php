<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fournisseurs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organisation_id');
            $table->string('nom', 200);
            $table->string('telephone', 30)->nullable();
            $table->string('email', 200)->nullable();
            $table->string('adresse', 500)->nullable();
            $table->text('note')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->foreign('organisation_id')->references('id')->on('organisations')->cascadeOnDelete();
            $table->index('organisation_id');
        });

        Schema::create('commandes_fournisseur', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('fournisseur_id');
            $table->enum('statut', ['brouillon', 'envoyee', 'recue', 'annulee'])->default('brouillon');
            $table->date('date_commande');
            $table->date('date_livraison_prevue')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->foreign('organisation_id')->references('id')->on('organisations')->cascadeOnDelete();
            $table->foreign('fournisseur_id')->references('id')->on('fournisseurs')->cascadeOnDelete();
            $table->index('organisation_id');
        });

        Schema::create('commandes_fournisseur_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('commande_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('quantite', 10, 3);
            $table->decimal('prix_unitaire', 10, 3)->nullable();
            $table->string('unite', 50);
            $table->timestamps();

            $table->foreign('commande_id')->references('id')->on('commandes_fournisseur')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commandes_fournisseur_items');
        Schema::dropIfExists('commandes_fournisseur');
        Schema::dropIfExists('fournisseurs');
    }
};
