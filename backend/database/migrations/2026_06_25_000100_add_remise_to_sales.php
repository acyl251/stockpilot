<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('remise_type', 20)->nullable()->after('total_ttc');   // pourcentage | montant
            $table->decimal('remise_valeur', 12, 3)->nullable()->after('remise_type');
            $table->decimal('remise_montant', 12, 3)->default(0)->after('remise_valeur');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['remise_type', 'remise_valeur', 'remise_montant']);
        });
    }
};
