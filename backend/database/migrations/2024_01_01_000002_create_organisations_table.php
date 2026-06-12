<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organisations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('plans');
            $table->string('nom', 200);
            $table->string('secteur', 100)->nullable();
            $table->string('email_contact', 255)->unique();
            $table->string('telephone', 20)->nullable();
            $table->string('adresse', 500)->nullable();
            $table->string('matricule_fiscal', 20)->nullable();
            $table->boolean('onboarding_complete')->default(false);
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        if (DB::connection()->getDriverName() === 'oracle') {
            DB::statement("COMMENT ON TABLE organisations IS 'Tenants SaaS — chaque PME est une organisation'");
            DB::statement("
                CREATE OR REPLACE TRIGGER trg_organisations_updated_at
                BEFORE UPDATE ON organisations
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
            DB::statement('DROP TRIGGER trg_organisations_updated_at');
        }
        Schema::dropIfExists('organisations');
    }
};
