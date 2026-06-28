<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->string('nom', 200);
            $table->decimal('prix_vente', 12, 3);
            $table->foreignId('ingredient_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('quantite', 12, 3);
            $table->string('unite', 30)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('organisation_id', 'idx_supplements_org');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplements');
    }
};
