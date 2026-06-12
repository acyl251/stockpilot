<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained('organisations');
            $table->string('nom', 100);
            $table->string('prenom', 100);
            $table->string('email', 255)->unique();
            $table->string('password', 255);
            $table->string('role', 20)->default('operateur');
            $table->boolean('actif')->default(true);
            $table->unsignedSmallInteger('tentatives_connexion')->default(0);
            $table->timestamp('verrouille_jusqu_a')->nullable();
            $table->timestamps();

            $table->index('organisation_id', 'idx_users_org');
            $table->index('email', 'idx_users_email');
        });

        if (DB::connection()->getDriverName() === 'oracle') {
            DB::statement("
                CREATE OR REPLACE TRIGGER trg_users_updated_at
                BEFORE UPDATE ON users
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
            DB::statement('DROP TRIGGER trg_users_updated_at');
        }
        Schema::dropIfExists('users');
    }
};
