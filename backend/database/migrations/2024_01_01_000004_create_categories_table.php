<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained('organisations');
            $table->string('nom', 150);
            $table->string('description', 500)->nullable();
            $table->string('couleur', 7)->default('#C9A84C');
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->unique(['organisation_id', 'nom'], 'uq_categories_nom');
            $table->index('organisation_id', 'idx_categories_org');
        });

        if (DB::connection()->getDriverName() === 'oracle') {
            DB::statement("
                CREATE OR REPLACE TRIGGER trg_categories_updated_at
                BEFORE UPDATE ON categories
                FOR EACH ROW
                BEGIN
                    :NEW.updated_at := SYSTIMESTAMP;
                END;
            ");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'oracle') {
            DB::statement('DROP TRIGGER trg_categories_updated_at');
        }
        Schema::dropIfExists('categories');
    }
};
