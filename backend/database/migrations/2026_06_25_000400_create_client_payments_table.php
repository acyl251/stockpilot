<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained('organisations');
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->decimal('montant', 12, 3);
            $table->string('mode_paiement', 20)->default('especes'); // especes | carte
            $table->string('note', 255)->nullable();
            $table->timestamp('date_paiement')->useCurrent();
            $table->timestamps();

            $table->index('organisation_id', 'idx_cpay_org');
            $table->index('client_id', 'idx_cpay_client');
        });

        if (DB::connection()->getDriverName() === 'oracle') {
            DB::statement("
                CREATE OR REPLACE TRIGGER trg_client_payments_updated_at
                BEFORE UPDATE ON client_payments
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
            DB::statement('DROP TRIGGER trg_client_payments_updated_at');
        }
        Schema::dropIfExists('client_payments');
    }
};
