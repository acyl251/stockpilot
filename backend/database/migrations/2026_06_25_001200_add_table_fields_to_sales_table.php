<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('table_id')
                ->nullable()
                ->after('client_id')
                ->constrained('tables_restaurant')
                ->nullOnDelete();
            $table->string('type_commande', 20)->nullable()->after('table_id');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('table_id');
            $table->dropColumn('type_commande');
        });
    }
};
