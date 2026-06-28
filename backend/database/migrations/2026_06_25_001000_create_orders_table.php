<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignId('table_id')->nullable()->constrained('tables_restaurant')->nullOnDelete();
            $table->enum('type', ['sur_place', 'emporter'])->default('sur_place');
            $table->enum('statut', ['en_cours', 'envoyee_cuisine', 'payee', 'annulee'])->default('en_cours');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->index('organisation_id', 'idx_orders_org');
            $table->index(['table_id', 'statut'], 'idx_orders_table_statut');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
