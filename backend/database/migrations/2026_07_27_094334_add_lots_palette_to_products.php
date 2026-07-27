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
        Schema::table('products', function (Blueprint $table) {
            $table->string('unite_vente')->nullable()->after('prix_vente_gros');
            $table->integer('quantite_par_lot')->nullable()->after('unite_vente');
            $table->integer('lots_par_palette')->nullable()->after('quantite_par_lot');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['unite_vente', 'quantite_par_lot', 'lots_par_palette']);
        });
    }
};
