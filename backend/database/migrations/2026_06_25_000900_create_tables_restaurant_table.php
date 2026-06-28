<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tables_restaurant', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->string('numero', 50);
            $table->unsignedInteger('capacite')->nullable();
            $table->enum('statut', ['libre', 'occupee'])->default('libre');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index('organisation_id', 'idx_tables_restaurant_org');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tables_restaurant');
    }
};
