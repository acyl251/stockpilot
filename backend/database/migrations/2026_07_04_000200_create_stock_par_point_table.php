<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_par_point', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('point_de_vente_id')->constrained('points_de_vente')->cascadeOnDelete();
            $table->decimal('quantite', 12, 3)->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'point_de_vente_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_par_point');
    }
};
