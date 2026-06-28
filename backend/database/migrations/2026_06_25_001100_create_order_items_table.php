<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('supplement_id')->nullable()->constrained('supplements')->nullOnDelete();
            $table->string('designation', 200);
            $table->unsignedInteger('quantite')->default(1);
            $table->decimal('prix_unitaire', 10, 3);
            $table->text('note_ligne')->nullable();
            $table->timestamps();
            $table->index('order_id', 'idx_order_items_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
