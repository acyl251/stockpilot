<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('demo_requests', function (Blueprint $table) {
            $table->id();
            $table->string('prenom');
            $table->string('nom');
            $table->string('email');
            $table->string('telephone')->nullable();
            $table->string('societe');
            $table->string('secteur')->nullable();
            $table->string('plan_souhaite')->nullable();
            $table->text('message')->nullable();
            $table->enum('statut', ['pending_verification', 'en_attente', 'traite', 'rejete'])->default('en_attente');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('demo_requests');
    }
};
