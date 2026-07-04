<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transferts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('point_source_id')->constrained('points_de_vente')->restrictOnDelete();
            $table->foreignId('point_dest_id')->constrained('points_de_vente')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('transfert_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfert_id')->constrained('transferts')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantite', 12, 3);
            $table->string('unite', 30)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfert_items');
        Schema::dropIfExists('transferts');
    }
};
