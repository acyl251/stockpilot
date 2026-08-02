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
            $table->string('code_barres', 100)->nullable()->after('reference');
            $table->unique(['organisation_id', 'code_barres'], 'idx_products_code_barres_org');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique('idx_products_code_barres_org');
            $table->dropColumn('code_barres');
        });
    }
};
