<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('point_de_vente_id')->nullable()->after('user_id')
                ->constrained('points_de_vente')->nullOnDelete();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('point_de_vente_id')->nullable()->after('user_id')
                ->constrained('points_de_vente')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('point_de_vente_id')->nullable()->after('role')
                ->constrained('points_de_vente')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['point_de_vente_id']);
            $table->dropColumn('point_de_vente_id');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['point_de_vente_id']);
            $table->dropColumn('point_de_vente_id');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['point_de_vente_id']);
            $table->dropColumn('point_de_vente_id');
        });
    }
};
