<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `secteur` existe déjà (indice libre pour le seeding IA). On le réutilise comme
 * type d'activité de gating : 'commerce' (défaut) | 'restauration'.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('organisations', 'secteur')) {
            Schema::table('organisations', function (Blueprint $table) {
                $table->string('secteur', 100)->nullable()->default('commerce')->change();
            });
        } else {
            Schema::table('organisations', function (Blueprint $table) {
                $table->string('secteur', 100)->default('commerce');
            });
        }

        DB::table('organisations')->whereNull('secteur')->update(['secteur' => 'commerce']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('organisations', 'secteur')) {
            Schema::table('organisations', function (Blueprint $table) {
                $table->string('secteur', 100)->nullable()->default(null)->change();
            });
        }
    }
};
