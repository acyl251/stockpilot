<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->after('user_id')
                ->constrained('clients')->nullOnDelete();
            // Montant déjà réglé sur cette vente (= total_ttc pour especes/carte).
            $table->decimal('montant_regle', 12, 3)->default(0)->after('monnaie_rendue');
        });

        // Les ventes existantes (especes/carte) sont entièrement réglées.
        DB::table('sales')->update(['montant_regle' => DB::raw('total_ttc')]);
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
            $table->dropColumn('montant_regle');
        });
    }
};
