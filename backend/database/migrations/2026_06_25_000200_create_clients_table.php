<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained('organisations');
            $table->string('nom', 150);
            $table->string('telephone', 30)->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index('organisation_id', 'idx_clients_org');
            $table->index(['organisation_id', 'nom'], 'idx_clients_nom');
        });

        if (DB::connection()->getDriverName() === 'oracle') {
            DB::statement("
                CREATE OR REPLACE TRIGGER trg_clients_updated_at
                BEFORE UPDATE ON clients
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
            DB::statement('DROP TRIGGER trg_clients_updated_at');
        }
        Schema::dropIfExists('clients');
    }
};
