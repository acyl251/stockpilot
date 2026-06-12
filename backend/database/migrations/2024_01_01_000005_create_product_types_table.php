<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained('organisations');
            $table->string('nom', 150);
            $table->string('icone', 50)->nullable();
            $table->string('description', 500)->nullable();
            $table->boolean('suggere_par_ia')->default(false);
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->unique(['organisation_id', 'nom'], 'uq_product_types_nom');
            $table->index('organisation_id', 'idx_product_types_org');
        });

        if (DB::connection()->getDriverName() === 'oracle') {
            DB::statement("
                CREATE OR REPLACE TRIGGER trg_product_types_updated_at
                BEFORE UPDATE ON product_types
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
            DB::statement('DROP TRIGGER trg_product_types_updated_at');
        }
        Schema::dropIfExists('product_types');
    }
};
