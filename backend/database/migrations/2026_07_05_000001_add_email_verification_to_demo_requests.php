<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demo_requests', function (Blueprint $table) {
            $table->string('email_token', 64)->nullable()->unique()->after('message');
            $table->timestamp('email_verified_at')->nullable()->after('email_token');
        });

        // Étend l'enum pour inclure pending_verification (MySQL uniquement)
        // SQLite ne contraint pas les enums → pas besoin d'ALTER
        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE demo_requests MODIFY COLUMN statut ENUM('pending_verification','en_attente','traite','rejete') NOT NULL DEFAULT 'pending_verification'");
        }
    }

    public function down(): void
    {
        Schema::table('demo_requests', function (Blueprint $table) {
            $table->dropColumn(['email_token', 'email_verified_at']);
        });

        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE demo_requests MODIFY COLUMN statut ENUM('en_attente','traite','rejete') NOT NULL DEFAULT 'en_attente'");
        }
    }
};
