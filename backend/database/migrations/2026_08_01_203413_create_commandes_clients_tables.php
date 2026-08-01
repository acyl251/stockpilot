<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commandes_clients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('client_id')->nullable();
            $table->string('nom_client', 150)->nullable();
            $table->string('telephone_client', 30)->nullable();
            $table->string('adresse_livraison', 500)->nullable();
            $table->enum('statut', ['en_preparation', 'prete', 'livree', 'payee', 'annulee'])->default('en_preparation');
            $table->text('note')->nullable();
            $table->string('numero_bon', 30)->unique();
            $table->decimal('total_ttc', 12, 3)->default(0);
            $table->enum('type_paiement', ['cash', 'credit'])->nullable();
            $table->unsignedBigInteger('sale_id')->nullable();
            $table->timestamps();

            $table->foreign('organisation_id')->references('id')->on('organisations')->cascadeOnDelete();
            $table->foreign('client_id')->references('id')->on('clients')->nullOnDelete();
            $table->foreign('sale_id')->references('id')->on('sales')->nullOnDelete();
            $table->index('organisation_id');
            $table->index(['organisation_id', 'statut']);
        });

        Schema::create('commande_client_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('commande_client_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('quantite', 12, 3);
            $table->decimal('prix_unitaire', 12, 3);
            $table->enum('type_prix', ['detail', 'gros'])->default('detail');
            $table->decimal('total', 12, 3);
            $table->timestamps();

            $table->foreign('commande_client_id')->references('id')->on('commandes_clients')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commande_client_items');
        Schema::dropIfExists('commandes_clients');
    }
};
