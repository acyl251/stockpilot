<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('type_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained('organisations');
            $table->foreignId('product_type_id')->constrained('product_types');
            $table->string('nom', 100);
            $table->string('label', 150);
            $table->string('type_donnee', 20);
            $table->boolean('obligatoire')->default(false);
            $table->string('valeur_defaut', 500)->nullable();
            $table->string('options_select', 1000)->nullable();
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();

            $table->index('product_type_id', 'idx_type_attrs_type');
            $table->index('organisation_id', 'idx_type_attrs_org');
        });

        if (DB::connection()->getDriverName() === 'oracle') {
            DB::statement("
                CREATE OR REPLACE TRIGGER trg_type_attributes_updated_at
                BEFORE UPDATE ON type_attributes
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
            DB::statement('DROP TRIGGER trg_type_attributes_updated_at');
        }
        Schema::dropIfExists('type_attributes');
    }
};
